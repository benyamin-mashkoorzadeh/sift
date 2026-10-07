export type AssistantInteractionStatus = "answered" | "needs_review";
export type AssistantInteractionFilter = "all" | AssistantInteractionStatus;
export type AssistantInteractionOrigin = "assistant" | "widget";

export interface AssistantInteractionCitation {
  document_id: number | null;
  chunk_id: number | null;
  original_filename: string;
  page_number: number;
  chunk_index: number;
  source_available: boolean;
}

export interface AssistantInteractionReview {
  id: number;
  status: "pending" | "resolved";
  resolution: string | null;
  resolved_at: string | null;
}

export interface AssistantInteraction {
  id: number;
  origin: AssistantInteractionOrigin;
  status: AssistantInteractionStatus;
  question: string;
  answer: string;
  citations: AssistantInteractionCitation[];
  review: AssistantInteractionReview | null;
  created_at: string;
}
