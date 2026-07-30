import { apiFetch } from "@/lib/auth-api";

export type ReadinessCheck = { key: string; status: "passed" | "warning" | "failed"; critical: boolean; message: string };
export type ReadinessReport = { status: "passed" | "warning" | "failed"; checks: ReadinessCheck[]; summary: { passed: number; warnings: number; failed: number } };
export type SystemBackup = { id: string; type: string; status: string; checksum_sha256: string | null; file_size: number | null; encrypted: boolean; started_at: string; completed_at: string | null; verified_at: string | null; expires_at: string | null; error_message: string | null };
export type RestoreDrill = { id: string; system_backup_id: string; status: string; checksum_verified: boolean; decryption_verified: boolean; archive_verified: boolean; inspected_bytes: number; started_at: string; completed_at: string | null; error_message: string | null };
export type ReleaseCheck = { id: string; status: string; environment: string; version: string | null; summary: { passed: number; warnings: number; failed: number }; started_at: string; completed_at: string | null };
export type ReliabilityDashboard = { readiness: ReadinessReport; backups: SystemBackup[]; restore_drills: RestoreDrill[]; release_checks: ReleaseCheck[] };

export const reliabilityApi = {
  dashboard: () => apiFetch<{ data: ReliabilityDashboard }>("/api/v1/admin/reliability"),
  backup: () => apiFetch<{ data: SystemBackup }>("/api/v1/admin/reliability/backups", { method: "POST" }),
  verify: (backupId: string) => apiFetch<{ data: RestoreDrill }>(`/api/v1/admin/reliability/backups/${backupId}/verify`, { method: "POST" }),
  check: () => apiFetch<{ data: ReleaseCheck }>("/api/v1/admin/reliability/checks", { method: "POST" }),
};
