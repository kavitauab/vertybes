<?php
/**
 * Migration: prune UI texts left over from retired screens (the old privacy
 * consent screen wording, the removed AI-review screen, standalone policy
 * page copy that now lives in policy.*). Keeps the Tekstai list honest —
 * every remaining key is something the app actually renders.
 */

class PruneDeadTextsMigration {
    public function up($db) {
        $db->query("DELETE FROM ui_texts WHERE text_key IN (
            'privacy.title','privacy.body','privacy.confirm','privacy.link',
            'privacy.yes','privacy.no','privacy.declined',
            'privacy.page.title','privacy.page.body',
            'values.review.title','values.review.help',
            'values.review.searchPlaceholder','values.review.loading',
            'questions.savedAll')");
    }

    public function down($db) {
        // Content cleanup — no rollback.
    }
}
