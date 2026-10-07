"use client";

import {
  AlertCircle,
  Check,
  CheckCircle2,
  Clipboard,
  Link2,
  LoaderCircle,
  MailPlus,
  RefreshCw,
  Trash2,
  UserRoundMinus,
  Users,
  X,
} from "lucide-react";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import {
  cancelWorkspaceInvitation,
  createWorkspaceInvitation,
  regenerateWorkspaceInvitation,
  removeWorkspaceMember,
  TeamApiError,
  updateWorkspaceMemberRole,
} from "@/lib/team";
import type {
  ManageableWorkspaceRole,
  WorkspaceInvitation,
  WorkspaceInvitationLink,
  WorkspaceMember,
} from "@/types/team";

const primaryButtonClass = "inline-flex min-h-9.25 cursor-pointer items-center justify-center gap-1.75 rounded-lg border border-primary bg-primary px-3 text-[11px] font-bold text-surface transition-[color,border-color,background] duration-160 hover:not-disabled:border-primary-hover hover:not-disabled:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-52 motion-reduce:transition-none";
const secondaryButtonClass = "inline-flex min-h-9.25 cursor-pointer items-center justify-center gap-1.75 rounded-lg border border-border bg-elevated px-3 text-[11px] font-bold text-secondary transition-[color,border-color,background] duration-160 hover:not-disabled:border-primary hover:not-disabled:text-primary disabled:cursor-not-allowed disabled:opacity-52 motion-reduce:transition-none";
const rowActionClass = "inline-flex min-h-8.25 cursor-pointer items-center justify-center gap-1.75 rounded-lg border border-border bg-elevated px-2.25 text-[11px] font-bold text-secondary transition-[color,border-color,background] duration-160 hover:not-disabled:border-primary hover:not-disabled:text-primary disabled:cursor-not-allowed disabled:opacity-52 motion-reduce:transition-none";
const sectionClass = "overflow-hidden rounded-[13px] border border-border bg-surface";
const tableClass = "w-full min-w-190 border-collapse [&_th]:bg-elevated [&_th]:px-5.5 [&_th]:py-2.75 [&_th]:text-left [&_th]:text-[9px] [&_th]:font-bold [&_th]:tracking-[.1em] [&_th]:text-muted [&_th]:uppercase [&_th:last-child]:text-right [&_td]:border-t [&_td]:border-border [&_td]:px-5.5 [&_td]:py-3.75 [&_td]:text-[11px] [&_td]:text-secondary [&_td]:align-middle [&_tbody_tr:first-child_td]:border-t-0 [&_td:first-child_strong]:block [&_td:first-child_strong]:text-xs [&_td:first-child_strong]:text-foreground [&_td:first-child_span]:mt-1 [&_td:first-child_span]:block [&_td:first-child_span]:text-[10px] [&_td:first-child_span]:text-muted [&_td:last-child]:w-70 [&_td:last-child]:text-right max-[680px]:[&_th]:px-3.75 max-[680px]:[&_td]:px-3.75";
const dialogClass = "relative w-full max-w-130 rounded-[14px] border border-border bg-surface p-7 shadow-[0_24px_70px_rgba(54,43,46,.2)] max-[680px]:px-4.5 max-[680px]:py-6 [&>h2]:m-0 [&>h2]:text-[23px] [&>h2]:tracking-[-.03em]";
const inputClass = "min-h-10.75 w-full rounded-lg border border-border bg-background px-2.75 text-xs text-foreground outline-none focus:border-primary focus:shadow-[0_0_0_3px_var(--focus-ring)]";
const dialogActionsClass = "mt-1.75 flex justify-end gap-2 border-t border-border pt-4.25";
const linkSuccessClass = "mt-5.5 [&>span]:grid [&>span]:size-11.25 [&>span]:place-items-center [&>span]:rounded-[11px] [&>span]:bg-success-soft [&>span]:text-success [&>h3]:mt-3.25 [&>h3]:mb-0 [&>h3]:text-[15px] [&>p]:mt-1.75 [&>p]:mb-0 [&>p]:text-[11px] [&>p]:leading-[1.55] [&>p]:text-secondary";

interface TeamPanelProps {
  invitations: WorkspaceInvitation[];
  members: WorkspaceMember[];
  workspaceId: number;
}

export function TeamPanel({ invitations: initialInvitations, members: initialMembers, workspaceId }: TeamPanelProps) {
  const router = useRouter();
  const [members, setMembers] = useState(initialMembers);
  const [invitations, setInvitations] = useState(initialInvitations);
  const [inviteOpen, setInviteOpen] = useState(false);
  const [generatedLink, setGeneratedLink] = useState<WorkspaceInvitationLink | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busyKey, setBusyKey] = useState<string | null>(null);

  function changed(message: string) {
    setNotice(message);
    setError(null);
    router.refresh();
  }

  async function changeRole(member: WorkspaceMember, role: ManageableWorkspaceRole) {
    if (member.role === role) return;
    if (!window.confirm(`Change ${member.name}'s role to ${capitalize(role)}?`)) return;

    setBusyKey(`member-${member.id}`);
    setError(null);
    try {
      const updated = await updateWorkspaceMemberRole(workspaceId, member.id, role);
      setMembers((current) => current.map((item) => item.id === updated.id ? updated : item));
      changed(`${member.name} is now ${capitalize(role)}.`);
    } catch (requestError) {
      setError(teamErrorMessage(requestError));
    } finally {
      setBusyKey(null);
    }
  }

  async function removeMember(member: WorkspaceMember) {
    if (!window.confirm("Remove this person from the workspace? Their Sift account will not be deleted.")) return;

    setBusyKey(`member-${member.id}`);
    setError(null);
    try {
      await removeWorkspaceMember(workspaceId, member.id);
      setMembers((current) => current.filter((item) => item.id !== member.id));
      changed(`${member.name} was removed from this workspace.`);
    } catch (requestError) {
      setError(teamErrorMessage(requestError));
    } finally {
      setBusyKey(null);
    }
  }

  async function cancelInvitation(invitation: WorkspaceInvitation) {
    if (!window.confirm(`Cancel the invitation for ${invitation.email}?`)) return;

    setBusyKey(`invitation-${invitation.id}`);
    setError(null);
    try {
      await cancelWorkspaceInvitation(workspaceId, invitation.id);
      setInvitations((current) => current.filter((item) => item.id !== invitation.id));
      changed(`Invitation for ${invitation.email} cancelled.`);
    } catch (requestError) {
      setError(teamErrorMessage(requestError));
    } finally {
      setBusyKey(null);
    }
  }

  async function regenerateInvitation(invitation: WorkspaceInvitation) {
    if (!window.confirm("Generate a new invitation link? The previous link will stop working.")) return;

    setBusyKey(`invitation-${invitation.id}`);
    setError(null);
    try {
      const result = await regenerateWorkspaceInvitation(workspaceId, invitation.id);
      setInvitations((current) => current.map((item) => item.id === invitation.id ? result.invitation : item));
      setGeneratedLink(result);
      changed(`A new invitation link for ${invitation.email} was generated.`);
    } catch (requestError) {
      setError(teamErrorMessage(requestError));
    } finally {
      setBusyKey(null);
    }
  }

  return (
    <div className="mt-7.5 grid gap-4">
      <div className="flex items-center justify-between gap-4.5 max-[680px]:flex-col max-[680px]:items-stretch [&>div]:text-xs [&>div]:text-secondary [&_strong]:font-mono [&_strong]:text-foreground">
        <div><strong>{members.length}</strong> {members.length === 1 ? "person" : "people"} in this workspace</div>
        <button className={`${primaryButtonClass} max-[680px]:w-full`} onClick={() => setInviteOpen(true)} type="button">
          <MailPlus aria-hidden="true" size={16} /> Invite member
        </button>
      </div>

      {notice && <div className="flex items-center gap-2.25 rounded-[9px] border border-[#b8d0c5] bg-success-soft px-3.25 py-2.75 text-xs text-success" role="status"><CheckCircle2 aria-hidden="true" size={17} />{notice}</div>}
      {error && <div className="flex items-center gap-2.25 rounded-[9px] border border-[#dcb9bc] bg-danger-soft px-3.25 py-2.75 text-xs text-danger" role="alert"><AlertCircle aria-hidden="true" size={17} />{error}</div>}

      <section className={sectionClass}>
        <div className="flex items-center justify-between gap-4.5 border-b border-border px-5.5 py-5 [&_h2]:m-0 [&_h2]:text-[15px] [&_h2]:tracking-[-.01em] [&_p]:mt-1.25 [&_p]:mb-0 [&_p]:text-[11px] [&_p]:leading-[1.5] [&_p]:text-secondary">
          <div><h2>Team members</h2><p>People who can currently access this workspace.</p></div>
        </div>
        <div className="overflow-x-auto">
          <table className={tableClass}>
            <thead><tr><th>Person</th><th>Role</th><th>Joined</th><th><span className="sr-only">Actions</span></th></tr></thead>
            <tbody>
              {members.map((member) => (
                <MemberRow
                  busy={busyKey === `member-${member.id}`}
                  key={member.id}
                  member={member}
                  onRemove={removeMember}
                  onRoleChange={changeRole}
                />
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className={sectionClass}>
        <div className="flex items-center justify-between gap-4.5 border-b border-border px-5.5 py-5 [&_h2]:m-0 [&_h2]:text-[15px] [&_h2]:tracking-[-.01em] [&_p]:mt-1.25 [&_p]:mb-0 [&_p]:text-[11px] [&_p]:leading-[1.5] [&_p]:text-secondary">
          <div><h2>Pending invitations</h2><p>Invitation links expire automatically and are shown only when generated.</p></div>
        </div>
        {invitations.length === 0 ? (
          <div className="grid min-h-52.5 place-items-center content-center p-7 text-center [&>span]:grid [&>span]:size-11.25 [&>span]:place-items-center [&>span]:rounded-[11px] [&>span]:border [&>span]:border-border [&>span]:bg-elevated [&>span]:text-primary [&>h3]:mt-3.25 [&>h3]:mb-0 [&>h3]:text-sm [&>p]:mt-1.5 [&>p]:mb-0 [&>p]:text-[11px] [&>p]:text-secondary">
            <span><Users aria-hidden="true" size={22} /></span>
            <h3>No pending invitations</h3>
            <p>Invite an Admin or Member when you are ready to add someone.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className={tableClass}>
              <thead><tr><th>Email</th><th>Role</th><th>Expires</th><th><span className="sr-only">Actions</span></th></tr></thead>
              <tbody>
                {invitations.map((invitation) => {
                  const busy = busyKey === `invitation-${invitation.id}`;
                  return (
                    <tr key={invitation.id}>
                      <td><strong>{invitation.email}</strong><span>Invited {formatDate(invitation.created_at)}</span></td>
                      <td><RoleBadge role={invitation.role} /></td>
                      <td><time dateTime={invitation.expires_at}>{formatDate(invitation.expires_at)}</time></td>
                      <td>
                        <div className="flex items-center justify-end gap-1.75">
                          <button className={rowActionClass} disabled={busy} onClick={() => regenerateInvitation(invitation)} type="button">
                            {busy ? <LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={14} /> : <RefreshCw aria-hidden="true" size={14} />} Regenerate & copy
                          </button>
                          <button className={`${rowActionClass} text-danger hover:not-disabled:border-danger hover:not-disabled:bg-danger-soft hover:not-disabled:text-danger`} disabled={busy} onClick={() => cancelInvitation(invitation)} type="button">
                            <Trash2 aria-hidden="true" size={14} /> Cancel
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {inviteOpen && (
        <InviteDialog
          onClose={() => setInviteOpen(false)}
          onCreated={(invitation) => {
            setInvitations((current) => [invitation, ...current]);
            changed(`Invitation for ${invitation.email} created.`);
          }}
          workspaceId={workspaceId}
        />
      )}
      {generatedLink && (
        <GeneratedLinkDialog link={generatedLink} onClose={() => setGeneratedLink(null)} />
      )}
    </div>
  );
}

function MemberRow({ busy, member, onRemove, onRoleChange }: {
  busy: boolean;
  member: WorkspaceMember;
  onRemove: (member: WorkspaceMember) => void;
  onRoleChange: (member: WorkspaceMember, role: ManageableWorkspaceRole) => void;
}) {
  const [selectedRole, setSelectedRole] = useState<ManageableWorkspaceRole>(member.role === "owner" ? "admin" : member.role);

  return (
    <tr>
      <td><strong>{member.name}</strong><span>{member.email}</span></td>
      <td>{member.role === "owner" ? <RoleBadge role="owner" /> : (
        <select className={`${inputClass} min-h-9.5 w-auto`} aria-label={`Role for ${member.name}`} disabled={busy} onChange={(event) => setSelectedRole(event.target.value as ManageableWorkspaceRole)} value={selectedRole}>
          <option value="admin">Admin</option><option value="member">Member</option>
        </select>
      )}</td>
      <td><time dateTime={member.joined_at}>{formatDate(member.joined_at)}</time></td>
      <td>{member.role === "owner" ? <span className="text-[10px] text-muted">Protected owner</span> : (
        <div className="flex items-center justify-end gap-1.75">
          <button className={rowActionClass} disabled={busy || selectedRole === member.role} onClick={() => onRoleChange(member, selectedRole)} type="button">
            {busy ? <LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={14} /> : <Check aria-hidden="true" size={14} />} Change role
          </button>
          <button className={`${rowActionClass} text-danger hover:not-disabled:border-danger hover:not-disabled:bg-danger-soft hover:not-disabled:text-danger`} disabled={busy} onClick={() => onRemove(member)} type="button">
            <UserRoundMinus aria-hidden="true" size={14} /> Remove
          </button>
        </div>
      )}</td>
    </tr>
  );
}

function InviteDialog({ onClose, onCreated, workspaceId }: {
  onClose: () => void;
  onCreated: (invitation: WorkspaceInvitation) => void;
  workspaceId: number;
}) {
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<ManageableWorkspaceRole>("member");
  const [link, setLink] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [requestError, setRequestError] = useState<string | null>(null);
  const [copyState, setCopyState] = useState<"idle" | "copied" | "failed">("idle");
  const [submitting, setSubmitting] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setFieldErrors({});
    setRequestError(null);
    try {
      const result = await createWorkspaceInvitation(workspaceId, email, role);
      setLink(result.invitation_url);
      onCreated(result.invitation);
    } catch (error) {
      if (error instanceof TeamApiError) {
        setFieldErrors(error.errors);
        setRequestError(Object.keys(error.errors).length === 0 ? error.message : null);
      } else {
        setRequestError("Sift could not reach the API. Check your connection and try again.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function copyLink() {
    if (!link) return;
    try {
      await copyText(link);
      setCopyState("copied");
    } catch {
      setCopyState("failed");
    }
  }

  return (
    <div className="fixed inset-0 z-60 grid place-items-center bg-[rgba(33,29,30,.48)] p-5" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <div aria-labelledby="invite-title" aria-modal="true" className={dialogClass} role="dialog">
        <button aria-label="Close invitation dialog" className="absolute top-4.5 right-4.5 grid size-8.5 cursor-pointer place-items-center rounded-lg border border-border bg-elevated text-secondary" onClick={onClose} type="button"><X aria-hidden="true" size={18} /></button>
        <p className="mt-0 mb-2.25 text-[11px] font-bold tracking-[.13em] text-primary uppercase">Workspace invitation</p>
        <h2 id="invite-title">Invite a team member</h2>
        {!link ? (
          <form aria-busy={submitting} className="mt-5.75 grid gap-3.5 [&>label]:grid [&>label]:gap-1.75 [&>label]:text-[11px] [&>label]:font-bold [&>label]:text-foreground" onSubmit={submit}>
            <label>Email<input className={inputClass} autoFocus onChange={(event) => setEmail(event.target.value)} required type="email" value={email} /></label>
            {fieldErrors.email?.[0] && <p className="-mt-1.75 mb-0 text-[10px] leading-[1.45] text-danger">{fieldErrors.email[0]}</p>}
            <label>Role<select className={inputClass} onChange={(event) => setRole(event.target.value as ManageableWorkspaceRole)} value={role}><option value="member">Member</option><option value="admin">Admin</option></select></label>
            {fieldErrors.role?.[0] && <p className="-mt-1.75 mb-0 text-[10px] leading-[1.45] text-danger">{fieldErrors.role[0]}</p>}
            <p className="-mt-1 mb-0 text-[10px] leading-[1.55] text-muted">{role === "admin" ? "Admins can manage knowledge and review activity, but not Team or Settings changes." : "Members can use Overview, Assistant, and Review."}</p>
            {requestError && <p className="m-0 flex gap-1.75 rounded-lg border border-[#dcb9bc] bg-danger-soft px-2.75 py-2.5 text-[11px] leading-[1.5] text-danger" role="alert"><AlertCircle aria-hidden="true" size={15} />{requestError}</p>}
            <div className={dialogActionsClass}><button className={secondaryButtonClass} disabled={submitting} onClick={onClose} type="button">Cancel</button><button className={primaryButtonClass} disabled={submitting} type="submit">{submitting ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={15} /> Creating…</> : <><MailPlus aria-hidden="true" size={15} /> Create invitation</>}</button></div>
          </form>
        ) : (
          <div className={linkSuccessClass}>
            <span><Link2 aria-hidden="true" size={21} /></span>
            <h3>Invitation created</h3>
            <p>Copy this link now. For security, Sift will not display this exact link again.</p>
            <div className="mt-4 grid gap-2.25 rounded-[9px] border border-border bg-background p-3 [&_code]:[overflow-wrap:anywhere] [&_code]:font-mono [&_code]:text-[9px] [&_code]:leading-[1.6] [&_code]:text-secondary"><code>{link}</code><button className={`${secondaryButtonClass} justify-self-start`} onClick={copyLink} type="button"><Clipboard aria-hidden="true" size={15} /> {copyState === "copied" ? "Copied" : "Copy link"}</button></div>
            {copyState === "failed" && <p className="-mt-1.75 mb-0 text-[10px] leading-[1.45] text-danger">The browser could not copy the link. Select and copy it manually.</p>}
            <div className={dialogActionsClass}><button className={primaryButtonClass} onClick={onClose} type="button">Done</button></div>
          </div>
        )}
      </div>
    </div>
  );
}

function GeneratedLinkDialog({ link, onClose }: { link: WorkspaceInvitationLink; onClose: () => void }) {
  const [copyState, setCopyState] = useState<"idle" | "copied" | "failed">("idle");

  async function copyLink() {
    try {
      await copyText(link.invitation_url);
      setCopyState("copied");
    } catch {
      setCopyState("failed");
    }
  }

  return (
    <div className="fixed inset-0 z-60 grid place-items-center bg-[rgba(33,29,30,.48)] p-5" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <div aria-labelledby="generated-link-title" aria-modal="true" className={dialogClass} role="dialog">
        <button aria-label="Close invitation link dialog" className="absolute top-4.5 right-4.5 grid size-8.5 cursor-pointer place-items-center rounded-lg border border-border bg-elevated text-secondary" onClick={onClose} type="button"><X aria-hidden="true" size={18} /></button>
        <p className="mt-0 mb-2.25 text-[11px] font-bold tracking-[.13em] text-primary uppercase">Replacement link</p>
        <h2 id="generated-link-title">New invitation link</h2>
        <div className={linkSuccessClass}>
          <span><Link2 aria-hidden="true" size={21} /></span>
          <h3>{link.invitation.email}</h3>
          <p>The previous link no longer works. Copy this replacement now; Sift will not display it again.</p>
          <div className="mt-4 grid gap-2.25 rounded-[9px] border border-border bg-background p-3 [&_code]:[overflow-wrap:anywhere] [&_code]:font-mono [&_code]:text-[9px] [&_code]:leading-[1.6] [&_code]:text-secondary"><code>{link.invitation_url}</code><button className={`${secondaryButtonClass} justify-self-start`} onClick={copyLink} type="button"><Clipboard aria-hidden="true" size={15} /> {copyState === "copied" ? "Copied" : "Copy link"}</button></div>
          {copyState === "failed" && <p className="-mt-1.75 mb-0 text-[10px] leading-[1.45] text-danger">The browser could not copy the link. Select and copy it manually.</p>}
          <div className={dialogActionsClass}><button className={primaryButtonClass} onClick={onClose} type="button">Done</button></div>
        </div>
      </div>
    </div>
  );
}

function RoleBadge({ role }: { role: WorkspaceMember["role"] }) {
  const roleClass = role === "owner" ? "border-primary-soft bg-primary-soft text-primary" : role === "admin" ? "bg-warning-soft text-warning" : "bg-elevated text-secondary";
  return <span className={`inline-flex min-h-6.25 items-center rounded-full border border-border px-2 text-[9px] font-extrabold tracking-[.04em] ${roleClass}`}>{capitalize(role)}</span>;
}

async function copyText(value: string): Promise<void> {
  if (!navigator.clipboard) throw new Error("Clipboard unavailable");
  await navigator.clipboard.writeText(value);
}

function teamErrorMessage(error: unknown): string {
  if (error instanceof TeamApiError) return error.message;
  return "Sift could not reach the API. Check your connection and try again.";
}

function capitalize(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat("en", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" }).format(new Date(value));
}
