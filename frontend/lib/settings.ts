import { sanctumFetch } from "@/lib/sanctum";
import type {
  WorkspaceSettings,
  WorkspaceSettingsErrorResponse,
  WorkspaceSettingsResponse,
  WorkspaceWidgetSettings,
  WorkspaceWidgetSettingsResponse,
} from "@/types/settings";

export class WorkspaceSettingsApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly nameError: string | null = null,
  ) {
    super(message);
    this.name = "WorkspaceSettingsApiError";
  }
}

export async function updateWorkspaceSettings(workspaceId: number, name: string): Promise<WorkspaceSettings> {
  const response = await sanctumFetch(`/workspaces/${workspaceId}/settings`, {
    method: "PATCH",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ name }),
  });
  const payload = await parseJson(response);

  if (!response.ok) {
    const error = payload as WorkspaceSettingsErrorResponse | null;
    const nameError = error?.errors?.name?.[0] ?? null;
    throw new WorkspaceSettingsApiError(
      response.status === 422
        ? nameError ?? "Check the workspace name and try again."
        : "The workspace name could not be saved. Please try again.",
      response.status,
      nameError,
    );
  }

  if (!isWorkspaceSettingsResponse(payload)) {
    throw new WorkspaceSettingsApiError("Sift returned an unexpected response. Please try again.", response.status);
  }

  return payload.data;
}

export async function provisionWorkspaceWidget(workspaceId: number): Promise<WorkspaceWidgetSettings> {
  return mutateWorkspaceWidget(`/workspaces/${workspaceId}/settings/widget`, "POST");
}

export async function setWorkspaceWidgetEnabled(
  workspaceId: number,
  enabled: boolean,
): Promise<WorkspaceWidgetSettings> {
  return mutateWorkspaceWidget(
    `/workspaces/${workspaceId}/settings/widget`,
    "PATCH",
    { enabled },
  );
}

export async function rotateWorkspaceWidgetKey(workspaceId: number): Promise<WorkspaceWidgetSettings> {
  return mutateWorkspaceWidget(`/workspaces/${workspaceId}/settings/widget/rotate-key`, "POST");
}

async function mutateWorkspaceWidget(
  path: string,
  method: "POST" | "PATCH",
  body?: Record<string, unknown>,
): Promise<WorkspaceWidgetSettings> {
  const response = await sanctumFetch(path, {
    method,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body ?? {}),
  });
  const payload = await parseJson(response);

  if (!response.ok) {
    throw new WorkspaceSettingsApiError(
      response.status === 403
        ? "You do not have permission to change Widget settings."
        : "The Customer Widget could not be updated. Please try again.",
      response.status,
    );
  }

  if (!isWorkspaceWidgetSettingsResponse(payload)) {
    throw new WorkspaceSettingsApiError("Sift returned an unexpected response. Please try again.", response.status);
  }

  return payload.data;
}

async function parseJson(response: Response): Promise<unknown> {
  try {
    return await response.json();
  } catch {
    return null;
  }
}

function isWorkspaceSettingsResponse(value: unknown): value is WorkspaceSettingsResponse {
  if (!isRecord(value) || !isRecord(value.data)) return false;

  const { data } = value;

  return isRecord(data.workspace)
    && typeof data.workspace.id === "number"
    && typeof data.workspace.name === "string"
    && typeof data.workspace.created_at === "string"
    && isRecord(data.knowledge)
    && Array.isArray(data.knowledge.supported_document_types)
    && typeof data.knowledge.maximum_upload_size_bytes === "number"
    && (data.widget === null || isWorkspaceWidgetSettings(data.widget));
}

function isWorkspaceWidgetSettingsResponse(value: unknown): value is WorkspaceWidgetSettingsResponse {
  return isRecord(value) && isWorkspaceWidgetSettings(value.data);
}

function isWorkspaceWidgetSettings(value: unknown): value is WorkspaceWidgetSettings {
  return isRecord(value)
    && typeof value.enabled === "boolean"
    && typeof value.public_key === "string"
    && typeof value.script_url === "string"
    && (value.key_rotated_at === null || typeof value.key_rotated_at === "string");
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}
