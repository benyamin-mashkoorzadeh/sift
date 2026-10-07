import type { AssistantInteractionStatus } from "@/types/assistant-interaction";
import type { DocumentStatus } from "@/types/document";

export interface WorkspaceOverview {
  workspace: {
    id: number;
    name: string;
  };
  documents: {
    total: number;
    ready: number;
    processing: number;
    failed: number;
  };
  interactions: {
    total: number;
    answered: number;
    needs_review: number;
  };
  reviews: {
    pending: number;
  };
  recent_documents: Array<{
    id: number;
    original_filename: string;
    status: DocumentStatus;
    page_count: number | null;
    size_bytes: number;
    created_at: string;
  }>;
  recent_interactions: Array<{
    id: number;
    question: string;
    status: AssistantInteractionStatus;
    created_at: string;
  }>;
}

export interface WorkspaceOverviewResponse {
  data: WorkspaceOverview;
}
