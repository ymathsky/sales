import AsyncStorage from '@react-native-async-storage/async-storage';
import { createTransaction, uploadReceipts } from '../api/client';

const DRAFTS_KEY = 'offline_transaction_drafts';

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
    return draft;
}

export async function removeTransactionDraft(localId) {
    const drafts = await getTransactionDrafts();
    const nextDrafts = drafts.filter(d => d.local_id !== localId);
    await setTransactionDrafts(nextDrafts);
}

export async function syncTransactionDrafts() {
    const drafts = await getTransactionDrafts();
    if (drafts.length === 0) {
        return { synced: 0, failed: 0, remaining: 0 };
    }

    let synced = 0;
    let failed = 0;
    const remainingDrafts = [];

    for (const draft of drafts) {
        try {
            const trxResult = await createTransaction(draft.payload);
            if (!trxResult.success) {
                failed += 1;
                remainingDrafts.push(draft);
                continue;
            }

            if (Array.isArray(draft.receipt_assets) && draft.receipt_assets.length > 0) {
                await uploadReceipts(trxResult.transaction_id, draft.receipt_assets);
            }

            synced += 1;
        } catch {
            failed += 1;
            remainingDrafts.push(draft);
        }
    }

    await setTransactionDrafts(remainingDrafts);

    return {
        synced,
        failed,
        remaining: remainingDrafts.length,
    };
}
