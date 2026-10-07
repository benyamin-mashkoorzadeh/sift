export type ReviewItemStatus = "pending" | "resolved";

export interface ReviewItem {
  id: number;
  status: ReviewItemStatus;
  question: string;
  resolution: string | null;
  created_at: string;
  last_asked_at: string;
  resolved_at: string | null;
}

export interface ReviewItemResponse {
  data: ReviewItem;
}

export interface ReviewErrorResponse {
  message?: string;
  code?: string;
  errors?: Record<string, string[]>;
}
