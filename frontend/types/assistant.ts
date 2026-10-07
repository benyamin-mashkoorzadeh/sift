export type AssistantAnswerStatus = "answered" | "needs_review";

export interface AssistantCitation {
  document_id: number;
  original_filename: string;
  page_number: number;
  chunk_id: number;
  chunk_index: number;
}

export interface AssistantAnswer {
  status: AssistantAnswerStatus;
  answer: string;
  citations: AssistantCitation[];
}

export interface AssistantAnswerResponse {
  data: AssistantAnswer;
}

export interface AssistantErrorResponse {
  message?: string;
  code?: string;
  errors?: Record<string, string[]>;
}
