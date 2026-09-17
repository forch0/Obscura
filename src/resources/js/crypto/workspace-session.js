// resources/js/crypto/workspace-session.js
// In-memory DEK storage keyed by workspace_id.
// Cleared on logout or tab close. Never persisted to disk/storage.

const dekStore = new Map();

/**
 * Store a DEK handle for a workspace.
 */
export function setWorkspaceDek(workspaceId, dekHandle) {
    dekStore.set(workspaceId, dekHandle);
}

/**
 * Retrieve the DEK handle for a workspace, or null.
 */
export function getWorkspaceDek(workspaceId) {
    return dekStore.get(workspaceId) ?? null;
}

/**
 * Check if a DEK is loaded for a workspace.
 */
export function hasWorkspaceDek(workspaceId) {
    return dekStore.has(workspaceId);
}

/**
 * Clear a single workspace's DEK.
 */
export function clearWorkspaceDek(workspaceId) {
    dekStore.delete(workspaceId);
}

/**
 * Clear all workspace DEKs (called on logout).
 */
export function clearAllWorkspaceDeks() {
    dekStore.clear();
}
