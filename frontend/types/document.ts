export type DocumentStatus = "processing" | "ready" | "failed";

export interface Document {
  id: number;
  workspace_id: number;
  original_filename: string;
  mime_type: string;
  size_bytes: number;
  status: DocumentStatus;
  page_count: number | null;
  processing_error: string | null;
  created_at: string;
}

export type { PaginatedResponse } from "@/types/api";

export interface ValidationErrorResponse {
  message?: string;
  errors?: Record<string, string[]>;
}
