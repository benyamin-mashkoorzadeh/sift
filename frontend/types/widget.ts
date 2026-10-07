export interface WidgetBootstrap {
  workspace_name: string;
}

export interface WidgetConversationSession {
  conversationToken: string;
  expiresAt: string;
}

export type WidgetAnswerStatus = "answered" | "needs_review";

export interface WidgetAnswer {
  status: WidgetAnswerStatus;
  answer: string;
}

export interface WidgetTranscriptMessage {
  id: string;
  role: "customer" | "assistant";
  content: string;
}
