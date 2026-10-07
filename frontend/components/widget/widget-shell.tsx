"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { WidgetLauncher } from "@/components/widget/widget-launcher";
import { WidgetPanel } from "@/components/widget/widget-panel";
import {
  sendWidgetQuestion,
  startWidgetConversation,
  WidgetApiError,
} from "@/lib/widget-api";
import {
  clearWidgetSession,
  readWidgetSession,
  saveWidgetSession,
} from "@/lib/widget-session";
import type { WidgetConversationSession, WidgetTranscriptMessage } from "@/types/widget";
import type { MutableRefObject } from "react";

interface WidgetShellProps {
  widgetKey: string;
  workspaceName?: string;
  unavailable?: boolean;
}

const NETWORK_ERROR = "We couldn't connect right now. Check your connection and try again.";

export function WidgetShell({ widgetKey, workspaceName, unavailable = false }: WidgetShellProps) {
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<WidgetTranscriptMessage[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const sessionRef = useRef<WidgetConversationSession | null>(null);
  const requestRef = useRef<AbortController | null>(null);
  const messageSequence = useRef(0);

  const sendPresentationState = useCallback((state: "launcher" | "open") => {
    const parentOrigin = readParentOrigin();
    if (!parentOrigin || window.parent === window) return;

    window.parent.postMessage({ type: "sift:widget:resize", state }, parentOrigin);
  }, []);

  useEffect(() => {
    sendPresentationState(open ? "open" : "launcher");
  }, [open, sendPresentationState]);

  useEffect(() => () => requestRef.current?.abort(), []);

  const submitQuestion = useCallback(async (question: string) => {
    if (requestRef.current || submitting || unavailable) return;

    const controller = new AbortController();
    requestRef.current = controller;
    setError(null);
    setSubmitting(true);
    setMessages((current) => [...current, message("customer", question, messageSequence)]);

    try {
      let session = sessionRef.current ?? readWidgetSession(widgetKey);
      if (!session) session = await createAndStoreSession(widgetKey, controller.signal);
      sessionRef.current = session;

      let answer;
      try {
        answer = await sendWidgetQuestion(
          widgetKey,
          session.conversationToken,
          question,
          controller.signal,
        );
      } catch (requestError) {
        if (!(requestError instanceof WidgetApiError)
          || requestError.code !== "conversation_unavailable") {
          throw requestError;
        }

        clearWidgetSession(widgetKey);
        sessionRef.current = null;
        session = await createAndStoreSession(widgetKey, controller.signal);
        sessionRef.current = session;
        answer = await sendWidgetQuestion(
          widgetKey,
          session.conversationToken,
          question,
          controller.signal,
        );
      }

      setMessages((current) => [...current, message("assistant", answer.answer, messageSequence)]);
    } catch (requestError) {
      if (requestError instanceof DOMException && requestError.name === "AbortError") return;
      setError(requestError instanceof WidgetApiError ? requestError.message : NETWORK_ERROR);
    } finally {
      if (requestRef.current === controller) {
        requestRef.current = null;
        setSubmitting(false);
      }
    }
  }, [submitting, unavailable, widgetKey]);

  if (!open) {
    return (
      <div className="grid h-full w-full place-items-end">
        <WidgetLauncher onOpen={() => setOpen(true)} unavailable={unavailable} />
      </div>
    );
  }

  return (
    <WidgetPanel
      error={error}
      messages={messages}
      onClose={() => setOpen(false)}
      onSubmit={submitQuestion}
      submitting={submitting}
      unavailable={unavailable}
      workspaceName={workspaceName ?? "Customer support"}
    />
  );
}

async function createAndStoreSession(
  widgetKey: string,
  signal: AbortSignal,
): Promise<WidgetConversationSession> {
  const session = await startWidgetConversation(widgetKey, signal);
  saveWidgetSession(widgetKey, session);

  return session;
}

function message(
  role: WidgetTranscriptMessage["role"],
  content: string,
  sequence: MutableRefObject<number>,
): WidgetTranscriptMessage {
  sequence.current += 1;

  return { id: `message-${sequence.current}`, role, content };
}

function readParentOrigin(): string | null {
  const value = new URLSearchParams(window.location.hash.slice(1)).get("parent-origin");
  if (!value) return null;

  try {
    const url = new URL(value);
    return url.protocol === "http:" || url.protocol === "https:" ? url.origin : null;
  } catch {
    return null;
  }
}
