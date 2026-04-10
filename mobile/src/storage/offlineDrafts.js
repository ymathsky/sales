import AsyncStorage from '@react-native-async-storage/async-storage';
import { createTransaction, uploadReceipts } from '../api/client';

const DRAFTS_KEY = 'offline_transaction_drafts';
const DRAFT_SYNC_STATUS_KEY = 'offline_transaction_draft_sync_status';

export async function getTransactionDrafts() {
    const raw = await AsyncStorage.getItem(DRAFTS_KEY);
    if (!raw) return [];

    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

async function setTransactionDrafts(drafts) {
    await AsyncStorage.setItem(DRAFTS_KEY, JSON.stringify(drafts));
}

async function setDraftSyncStatus(statusPatch) {
    const current = await getDraftSyncStatus();
    const next = {
        ...current,
        ...statusPatch,
    };
    await AsyncStorage.setItem(DRAFT_SYNC_STATUS_KEY, JSON.stringify(next));
    return next;
}

export async function getDraftSyncStatus() {
    const raw = await AsyncStorage.getItem(DRAFT_SYNC_STATUS_KEY);
    if (!raw) {
        const drafts = await getTransactionDrafts();
        return {
            pending_count: drafts.length,
            last_synced_at: null,
            last_attempt_at: null,
            last_error: null,
            last_result: null,
        };
    }

    try {
        const parsed = JSON.parse(raw);
        const drafts = await getTransactionDrafts();
        return {
            pending_count: drafts.length,
            last_synced_at: parsed.last_synced_at || null,
            last_attempt_at: parsed.last_attempt_at || null,
            last_error: parsed.last_error || null,
            last_result: parsed.last_result || null,
        };
    } catch {
        const drafts = await getTransactionDrafts();
        return {
            pending_count: drafts.length,
            last_synced_at: null,
            last_attempt_at: null,
            last_error: null,
            last_result: null,
        };
    }
}

export async function getPendingDraftCount() {
    const drafts = await getTransactionDrafts();
    return drafts.length;
}

export async function addTransactionDraft({ payload, receiptAssets = [] }) {
    const drafts = await getTransactionDrafts();
    const draft = {
        local_id: `draft_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
        payload,
        receipt_assets: receiptAssets,
        created_at: new Date().toISOString(),
    };

    drafts.unshift(draft);
    await setTransactionDrafts(drafts);
    await setDraftSyncStatus({ pending_count: drafts.length });
    return draft;
}

export async function removeTransactionDraft(localId) {
    const drafts = await getTransactionDrafts();
    const nextDrafts = drafts.filter(d => d.local_id !== localId);
    await setTransactionDrafts(nextDrafts);
    await setDraftSyncStatus({ pending_count: nextDrafts.length });
}

export async function syncTransactionDrafts() {
    const drafts = await getTransactionDrafts();

    await setDraftSyncStatus({
        pending_count: drafts.length,
        last_attempt_at: new Date().toISOString(),
        last_error: null,
    });

    if (drafts.length === 0) {
        const result = { synced: 0, failed: 0, remaining: 0, failed_reasons: [] };
        await setDraftSyncStatus({
            pending_count: 0,
            last_synced_at: new Date().toISOString(),
            last_result: result,
            last_error: null,
        });
        return result;
    }

    let synced = 0;
    let failed = 0;
    const remainingDrafts = [];
    const failedReasons = [];

    for (const draft of drafts) {
        try {
            const trxResult = await createTransaction(draft.payload);
            if (!trxResult.success) {
                failed += 1;
                remainingDrafts.push(draft);
                failedReasons.push({
                    local_id: draft.local_id,
                    reason: trxResult.error || 'Transaction sync failed',
                });
                continue;
            }

            if (Array.isArray(draft.receipt_assets) && draft.receipt_assets.length > 0) {
                const uploadResult = await uploadReceipts(trxResult.transaction_id, draft.receipt_assets);
                if (!uploadResult.success) {
                    failed += 1;
                    remainingDrafts.push(draft);
                    failedReasons.push({
                        local_id: draft.local_id,
                        reason: uploadResult.error || 'Receipt upload failed',
                    });
                    continue;
                }
            }

            synced += 1;
        } catch (error) {
            failed += 1;
            remainingDrafts.push(draft);
            failedReasons.push({
                local_id: draft.local_id,
                reason: error?.message || 'Network error while syncing draft',
            });
        }
    }

    await setTransactionDrafts(remainingDrafts);

    const result = {
        synced,
        failed,
        remaining: remainingDrafts.length,
        failed_reasons: failedReasons,
    };

    await setDraftSyncStatus({
        pending_count: remainingDrafts.length,
        last_synced_at: synced > 0 ? new Date().toISOString() : null,
        last_result: result,
        last_error: failedReasons[0]?.reason || null,
    });

    return result;
}
