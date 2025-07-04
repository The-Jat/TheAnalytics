<?php defined('ALTUMCODE') || die() ?>

<ul class="pricing-feature-list">
    <li class="pricing-feature d-flex align-items-baseline mb-2">
        <i class="fas fa-fw fa-check-circle fa-sm mr-3 text-success"></i>
        <div>
            <?= sprintf(l('global.plan_settings.websites_limit'), '<strong>' . ($data->plan_settings->websites_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->websites_limit)) . '</strong>') ?>
        </div>
    </li>

    <li class="pricing-feature d-flex align-items-baseline mb-2">
        <i class="fas fa-fw fa-check-circle fa-sm mr-3 text-success"></i>
        <div>
            <?= sprintf(l('global.plan_settings.sessions_events_limit'), '<strong>' . ($data->plan_settings->sessions_events_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->sessions_events_limit)) . '</strong>') . ' <br /> <small class="text-muted">' . l('global.plan_settings.per_month') . '</small>' ?>
        </div>
    </li>

    <li class="pricing-feature d-flex align-items-baseline mb-2">
        <i class="fas fa-fw fa-sm mr-3 fa-check-circle text-success"></i>
        <div data-toggle="tooltip" title="<?= ($data->plan_settings->sessions_events_retention == -1 ? '' : $data->plan_settings->sessions_events_retention . ' ' . l('global.date.days')) ?>">
            <?= sprintf(l('global.plan_settings.sessions_events_retention'), '<strong>' . ($data->plan_settings->sessions_events_retention == -1 ? l('global.unlimited') : \Altum\Date::days_format($data->plan_settings->sessions_events_retention)) . '</strong>') ?>
        </div>
    </li>

    <li class="pricing-feature d-flex align-items-baseline mb-2">
        <i class="fas fa-fw fa-sm mr-3 <?= !$data->plan_settings->events_children_limit ? 'fa-times-circle text-muted' : 'fa-check-circle text-success' ?>"></i>
        <div>
            <?= sprintf(l('global.plan_settings.events_children_limit'), '<strong>' . ($data->plan_settings->events_children_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->events_children_limit)) . '</strong>') . ' <br /> <small class="text-muted">' . l('global.plan_settings.per_month') . '</small>' ?>
        </div>
    </li>

    <?php if($data->plan_settings->events_children_limit != 0): ?>
        <li class="pricing-feature d-flex align-items-baseline mb-2">
            <i class="fas fa-fw fa-sm mr-3 fa-check-circle text-success"></i>
            <div data-toggle="tooltip" title="<?= ($data->plan_settings->events_children_retention == -1 ? '' : $data->plan_settings->events_children_retention . ' ' . l('global.date.days')) ?>">
                <?= sprintf(l('global.plan_settings.events_children_retention'), '<strong>' . ($data->plan_settings->events_children_retention == -1 ? l('global.unlimited') : \Altum\Date::days_format($data->plan_settings->events_children_retention)) . '</strong>') ?>
            </div>
        </li>
    <?php endif ?>

    <?php if(settings()->analytics->sessions_replays_is_enabled): ?>
        <li class="pricing-feature d-flex align-items-baseline mb-2">
            <i class="fas fa-fw fa-sm mr-3 <?= !$data->plan_settings->sessions_replays_limit ? 'fa-times-circle text-muted' : 'fa-check-circle text-success' ?>"></i>
            <div>
                <?= sprintf(l('global.plan_settings.sessions_replays_limit'), '<strong>' . ($data->plan_settings->sessions_replays_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->sessions_replays_limit)) . '</strong>') . ' <br /> <small class="text-muted">' . l('global.plan_settings.per_month') . '</small>' ?>
            </div>
        </li>

        <?php if($data->plan_settings->sessions_replays_limit != 0): ?>
            <li class="pricing-feature d-flex align-items-baseline mb-2">
                <i class="fas fa-fw fa-sm mr-3 fa-check-circle text-success"></i>
                <div data-toggle="tooltip" title="<?= ($data->plan_settings->sessions_replays_retention == -1 ? '' : $data->plan_settings->sessions_replays_retention . ' ' . l('global.date.days')) ?>">
                    <?= sprintf(l('global.plan_settings.sessions_replays_retention'), '<strong>' . ($data->plan_settings->sessions_replays_retention == -1 ? l('global.unlimited') : \Altum\Date::days_format($data->plan_settings->sessions_replays_retention)) . '</strong>') ?>
                </div>
            </li>
        <?php endif ?>
    <?php endif ?>

    <?php if(settings()->analytics->websites_heatmaps_is_enabled): ?>
        <li class="pricing-feature d-flex align-items-baseline mb-2">
            <i class="fas fa-fw fa-sm mr-3 <?= !$data->plan_settings->websites_heatmaps_limit ? 'fa-times-circle text-muted' : 'fa-check-circle text-success' ?>"></i>
            <div>
                <?= sprintf(l('global.plan_settings.websites_heatmaps_limit'), '<strong>' . ($data->plan_settings->websites_heatmaps_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->websites_heatmaps_limit)) . '</strong>') ?>
            </div>
        </li>
    <?php endif ?>

    <li class="pricing-feature d-flex align-items-baseline mb-2">
        <i class="fas fa-fw fa-sm mr-3 <?= !$data->plan_settings->websites_goals_limit ? 'fa-times-circle text-muted' : 'fa-check-circle text-success' ?>"></i>
        <div>
            <?= sprintf(l('global.plan_settings.websites_goals_limit'), '<strong>' . ($data->plan_settings->websites_goals_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->websites_goals_limit)) . '</strong>') ?>
        </div>
    </li>

    <?php if(settings()->analytics->domains_is_enabled): ?>
        <li class="pricing-feature d-flex align-items-baseline mb-2">
            <i class="fas fa-fw fa-sm mr-3 <?= !$data->plan_settings->domains_limit ? 'fa-times-circle text-muted' : 'fa-check-circle text-success' ?>"></i>
            <div>
                <?= sprintf(l('global.plan_settings.domains_limit'), '<strong>' . ($data->plan_settings->domains_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->domains_limit)) . '</strong>') ?>
            </div>
        </li>
    <?php endif ?>

    <?php if(\Altum\Plugin::is_active('affiliate') && settings()->affiliate->is_enabled): ?>
        <li class="pricing-feature d-flex align-items-baseline mb-2">
            <i class="fas fa-fw fa-sm mr-3 <?= !$data->plan_settings->affiliate_commission_percentage ? 'fa-times-circle text-muted' : 'fa-check-circle text-success' ?>"></i>
            <div>
                <?= sprintf(l('global.plan_settings.affiliate_commission_percentage'), '<strong>' . nr($data->plan_settings->affiliate_commission_percentage) . '%</strong>') ?>
            </div>
        </li>
    <?php endif ?>

    <?php if(settings()->analytics->email_reports_is_enabled): ?>
        <li class="pricing-feature d-flex align-items-baseline mb-2">
            <i class="fas fa-fw fa-sm mr-3 <?=$data->plan_settings->email_reports_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
            <div>
                <?= settings()->analytics->email_reports_is_enabled ? l('global.plan_settings.email_reports_is_enabled_' . settings()->analytics->email_reports_is_enabled) : l('global.plan_settings.email_reports_is_enabled') ?>
            </div>
        </li>
    <?php endif ?>

    <li class="pricing-feature d-flex align-items-baseline mb-2">
        <i class="fas fa-fw fa-sm mr-3 <?=$data->plan_settings->teams_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        <div>
            <?= l('global.plan_settings.teams_is_enabled') ?>
        </div>
    </li>

    <li class="pricing-feature d-flex align-items-baseline mb-2">
        <i class="fas fa-fw fa-sm mr-3 <?=$data->plan_settings->no_ads ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        <div>
            <?= l('global.plan_settings.no_ads') ?>
        </div>
    </li>

    <?php if(settings()->main->api_is_enabled): ?>
    <li class="pricing-feature d-flex align-items-baseline mb-2">
        <i class="fas fa-fw fa-sm mr-3 <?=$data->plan_settings->api_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        <div>
            <?= l('global.plan_settings.api_is_enabled') ?>
        </div>
    </li>
    <?php endif ?>

    <?php if(settings()->main->white_labeling_is_enabled): ?>
        <li class="pricing-feature d-flex align-items-baseline mb-2">
            <i class="fas fa-fw fa-sm mr-3 <?=$data->plan_settings->white_labeling_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
            <div>
                <?= l('global.plan_settings.white_labeling_is_enabled') ?>
            </div>
        </li>
    <?php endif ?>

    <?php $enabled_exports_count = count(array_filter((array) $data->plan_settings->export)); ?>

    <?php ob_start() ?>
    <div class='d-flex flex-column'>
        <?php foreach(['csv', 'json', 'pdf'] as $key): ?>
            <?php if($data->plan_settings->export->{$key}): ?>
                <span class='my-1'><?= sprintf(l('global.export_to'), mb_strtoupper($key)) ?></span>
            <?php else: ?>
                <s class='my-1'><?= sprintf(l('global.export_to'), mb_strtoupper($key)) ?></s>
            <?php endif ?>
        <?php endforeach ?>
    </div>
    <?php $html = ob_get_clean() ?>

    <li class="pricing-feature d-flex align-items-baseline mb-2">
        <i class="fas fa-fw fa-sm mr-3 <?=$enabled_exports_count ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        <div>
            <?= sprintf(l('global.plan_settings.export'), $enabled_exports_count) ?>
            <span class="mr-1" data-html="true" data-toggle="tooltip" title="<?= $html ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
        </div>
    </li>
</ul>
