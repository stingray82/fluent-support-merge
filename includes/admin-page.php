<?php defined('ABSPATH') || exit; ?>
<div class="wrap fscm-wrap">
    <div class="fscm-heading">
        <span class="fscm-eyebrow">Fluent Support · Customers</span>
        <h1>Merge customer records</h1>
        <p>Find two customer records, choose which one to keep, then review the tickets before confirming.</p>
    </div>

    <?php if (!$ready): ?>
        <div class="notice notice-error"><p>Fluent Support with its customer merge service must be active.</p></div>
    <?php else: ?>
        <?php if (isset($_GET['merged'])): ?>
            <div class="notice notice-success"><p><?php echo esc_html(absint($_GET['merged'])); ?> ticket(s) moved into the retained customer record.</p></div>
        <?php endif; ?>
        <?php if (($target_id && !$target) || ($source_id && !$source)): ?>
            <div class="notice notice-error"><p>One of the selected customer records was not found. Search again below.</p></div>
        <?php endif; ?>

        <div class="fscm-steps" aria-label="Merge steps">
            <span><b>1</b> Select records</span><span><b>2</b> Choose direction</span><span><b>3</b> Review and confirm</span>
        </div>

        <div class="fscm-picker" data-fscm-picker
             data-selected="<?php echo esc_attr(wp_json_encode($selected)); ?>"
             data-page-url="<?php echo esc_url($page_url); ?>">
            <?php foreach (['First customer', 'Second customer'] as $slot => $label): ?>
                <section class="fscm-select-card" data-fscm-slot="<?php echo esc_attr($slot); ?>">
                    <div class="fscm-card-top"><span class="fscm-step-number">0<?php echo esc_html($slot + 1); ?></span><div><h2><?php echo esc_html($label); ?></h2><p>Search by name, email, or customer ID</p></div></div>
                    <label class="screen-reader-text" for="fscm-search-<?php echo esc_attr($slot); ?>">Find <?php echo esc_html(strtolower($label)); ?></label>
                    <input id="fscm-search-<?php echo esc_attr($slot); ?>" type="search" class="regular-text fscm-search" placeholder="Start typing a customer…" autocomplete="off">
                    <div class="fscm-results" role="listbox" aria-label="<?php echo esc_attr($label); ?> search results" hidden></div>
                    <div class="fscm-selection" aria-live="polite"></div>
                </section>
            <?php endforeach; ?>
        </div>

        <section class="fscm-direction" data-fscm-direction hidden>
            <div class="fscm-section-heading"><span class="fscm-eyebrow">Choose direction</span><h2>Which customer record should stay?</h2><p>The other record will be merged into it.</p></div>
            <div class="fscm-direction-options">
                <label><input type="radio" name="fscm-keep" value="0" checked><span>Keep the first customer</span></label>
                <label><input type="radio" name="fscm-keep" value="1"><span>Keep the second customer</span></label>
            </div>
            <p class="fscm-flow" data-fscm-flow></p>
            <button type="button" class="button button-primary button-hero" data-fscm-review>Review this merge</button>
        </section>

        <?php if ($target && $source): ?>
            <section class="fscm-review" data-fscm-review-panel>
                <div class="fscm-section-heading"><span class="fscm-eyebrow">Final review</span><h2>Merge customer #<?php echo esc_html($source->id); ?> into customer #<?php echo esc_html($target->id); ?></h2>
                    <p><strong><?php echo esc_html($selected[1]['name']); ?></strong> and its tickets will move into <strong><?php echo esc_html($selected[0]['name']); ?></strong>. Customer #<?php echo esc_html($target->id); ?> stays.</p></div>
                <?php if ($reason): ?>
                    <div class="notice notice-error"><p><?php echo esc_html($reason); ?></p></div>
                <?php else: ?>
                    <div class="fscm-impact"><div><strong><?php echo esc_html(count($source_tickets)); ?></strong><span>tickets moving</span></div><div><strong><?php echo esc_html($selected[0]['tickets']); ?></strong><span>tickets already on the kept record</span></div></div>
                    <p>Replies, attachments, and customer metadata move with the record. Existing ticket access links will be replaced.</p>
                    <h3>Tickets to move</h3>
                    <ul class="fscm-ticket-list">
                        <?php foreach ($source_tickets as $ticket): ?><li><span>#<?php echo esc_html($ticket->serial_number ?: $ticket->id); ?></span><strong><?php echo esc_html($ticket->title); ?></strong></li><?php endforeach; ?>
                        <?php if (!count($source_tickets)): ?><li>No tickets on this record. Related customer data will still be merged.</li><?php endif; ?>
                    </ul>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="fscm_merge">
                        <input type="hidden" name="source" value="<?php echo esc_attr($source->id); ?>">
                        <input type="hidden" name="target" value="<?php echo esc_attr($target->id); ?>">
                        <?php wp_nonce_field('fscm_merge_' . $source->id . '_' . $target->id); ?>
                        <label class="fscm-confirm"><input type="checkbox" name="confirm" value="1" required><span>I have checked the direction and tickets above. Merge customer #<?php echo esc_html($source->id); ?> into customer #<?php echo esc_html($target->id); ?>.</span></label>
                        <button class="button button-primary button-hero">Confirm merge</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
