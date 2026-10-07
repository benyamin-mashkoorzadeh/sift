export interface WorkspaceSettings {
  workspace: {
    id: number;
    name: string;
    created_at: string;
  };
  knowledge: {
    supported_document_types: Array<{
      extension: string;
      label: string;
    }>;
    maximum_upload_size_bytes: number;
  };
  widget: WorkspaceWidgetSettings | null;
}

export interface WorkspaceWidgetSettings {
  enabled: boolean;
  public_key: string;
  script_url: string;
  key_rotated_at: string | null;
}

export interface WorkspaceWidgetSettingsResponse {
  data: WorkspaceWidgetSettings;
}

export interface WorkspaceSettingsResponse {
  data: WorkspaceSettings;
}

export interface WorkspaceSettingsErrorResponse {
  message?: string;
  errors?: Record<string, string[]>;
}
