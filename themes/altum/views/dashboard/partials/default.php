<?php defined('ALTUMCODE') || die() ?>

<div class="card border-0">
    <div class="card-body">
        <div class="chart-container">
            <canvas id="logs_chart"></canvas>
        </div>
    </div>
</div>

<div class="row mt-5">
    <div class="col-12 col-lg-6 col-xl-4 p-3">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <h2 class="h6 m-0"><?= l('dashboard.all_pages') ?></h2>

                        <a href="<?= url($data->base_url_path . 'paths') ?>" class="text-muted ml-3" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                    </div>
                    <span class="round-circle-sm bg-gray-200 text-primary-700 p-3" id="all_pages_icon">
                        <i class="fas fa-fw fa-sm fa-copy"></i>
                    </span>
                </div>

                <div style="font-size: .75rem">
                    <a href="#" id="paths_link" class="font-weight-bold active">
                        <?= l('dashboard.paths.header') ?>
                    </a>

                    &bull;

                    <a href="#" id="landing_paths_link" class="font-weight-bold text-muted">
                        <?= l('dashboard.landing_paths.header') ?>
                    </a>

                    &bull;

                    <a href="#" id="exit_paths_link" class="font-weight-bold text-muted">
                        <?= l('dashboard.exit_paths.header') ?>
                    </a>
                </div>

                <div class="mt-4" id="paths_result"></div>
                <div class="mt-4 d-none" id="landing_paths_result"></div>
                <div class="mt-4 d-none" id="exit_paths_result"></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6 col-xl-4 p-3">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <h2 class="h6 m-0"><?= l('dashboard.sources') ?></h2>

                        <a href="<?= url($data->base_url_path . 'referrers') ?>" id="referrers_view_more" class="text-muted ml-3" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                        <a href="<?= url($data->base_url_path . 'utms') ?>" id="utms_source_view_more" class="text-muted ml-3 d-none" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                    </div>
                    <span class="round-circle-sm bg-gray-200 text-primary-700 p-3" id="sources_icon">
                        <i class="fas fa-fw fa-sm fa-random"></i>
                    </span>
                </div>

                <div style="font-size: .75rem">
                    <a href="#" id="referrers_link" class="font-weight-bold active">
                        <?= l('dashboard.referrers.header') ?>
                    </a>

                    &bull;

                    <a href="#" id="utms_source_link" class="font-weight-bold text-muted">
                        <?= l('dashboard.utms.header') ?>
                    </a>
                </div>

                <div class="mt-4" id="referrers_result"></div>
                <div class="mt-4 d-none" id="utms_source_result"></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6 col-xl-4 p-3">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <h2 class="h6 m-0"><?= l('dashboard.locations') ?></h2>

                        <a href="<?= url($data->base_url_path . 'continents') ?>" id="continents_view_more" class="text-muted ml-3 d-none" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                        <a href="<?= url($data->base_url_path . 'countries') ?>" id="countries_view_more" class="text-muted ml-3" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                        <a href="<?= url($data->base_url_path . 'cities') ?>" id="cities_view_more" class="text-muted ml-3 d-none" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                    </div>
                    <span class="round-circle-sm bg-gray-200 text-primary-700 p-3" id="locations_icon">
                        <i class="fas fa-fw fa-sm fa-flag"></i>
                    </span>
                </div>

                <div style="font-size: .75rem">
                    <a href="#" id="continents_link" class="font-weight-bold text-muted">
                        <?= l('global.continents') ?>
                    </a>

                    &bull;

                    <a href="#" id="countries_link" class="font-weight-bold active">
                        <?= l('global.countries') ?>
                    </a>

                    &bull;

                    <a href="#" id="cities_link" class="font-weight-bold text-muted">
                        <?= l('global.cities') ?>
                    </a>
                </div>

                <div class="mt-4 d-none" id="continents_result"></div>
                <div class="mt-4" id="countries_result"></div>
                <div class="mt-4 d-none" id="cities_result"></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6 col-xl-4 p-3">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <h2 class="h6 m-0"><?= l('dashboard.operating_systems.header') ?></h2>

                        <a href="<?= url($data->base_url_path . 'operating-systems') ?>" class="text-muted ml-3" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                    </div>
                    <span class="round-circle-sm bg-gray-200 text-primary-700 p-3">
                        <i class="fas fa-fw fa-sm fa-server"></i>
                    </span>
                </div>

                <div style="font-size: .75rem">
                    &nbsp;
                </div>

                <div class="mt-4" id="operating_systems_result" data-limit="7"></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6 col-xl-4 p-3">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <h2 class="h6 m-0"><?= l('dashboard.devices') ?></h2>

                        <a href="<?= url($data->base_url_path . 'device-types') ?>" id="device_types_view_more" class="text-muted ml-3" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                        <a href="<?= url($data->base_url_path . 'screen-resolutions') ?>" id="screen_resolutions_view_more" class="text-muted ml-3 d-none" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                        <a href="<?= url($data->base_url_path . 'themes') ?>" id="themes_view_more" class="text-muted ml-3 d-none" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                    </div>
                    <span class="round-circle-sm bg-gray-200 text-primary-700 p-3" id="devices_icon">
                        <i class="fas fa-fw fa-sm fa-laptop"></i>
                    </span>
                </div>

                <div style="font-size: .75rem">
                    <a href="#" id="device_types_link" class="font-weight-bold active">
                        <?= l('dashboard.device_types.header') ?>
                    </a>

                    &bull;

                    <a href="#" id="screen_resolutions_link" class="font-weight-bold text-muted">
                        <?= l('dashboard.screen_resolutions.header') ?>
                    </a>

                    &bull;

                    <a href="#" id="themes_link" class="font-weight-bold text-muted">
                        <?= l('dashboard.themes.header') ?>
                    </a>
                </div>

                <div class="mt-4" id="device_types_result" data-limit="7"></div>
                <div class="mt-4 d-none" id="screen_resolutions_result" data-limit="7"></div>
                <div class="mt-4 d-none" id="themes_result" data-limit="7"></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6 col-xl-4 p-3">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <h2 class="h6 m-0"><?= l('dashboard.browser_names.header') ?></h2>

                        <a href="<?= url($data->base_url_path . 'browser-names') ?>" id="browser_names_view_more" class="text-muted ml-3" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                        <a href="<?= url($data->base_url_path . 'browser-languages') ?>" id="browser_languages_view_more" class="text-muted ml-3 d-none" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                        <a href="<?= url($data->base_url_path . 'browser-timezones') ?>" id="browser_timezones_view_more" class="text-muted ml-3 d-none" data-toggle="tooltip" title="<?= l('global.view_more') ?>"><i class="align-self-end fas fa-arrows-alt-h fa-sm text-gray"></i></a>
                    </div>
                    <span class="round-circle-sm bg-gray-200 text-primary-700 p-3" id="browsers_icon">
                        <i class="fas fa-fw fa-sm fa-window-restore"></i>
                    </span>
                </div>

                <div style="font-size: .75rem">
                    <a href="#" id="browser_names_link" class="font-weight-bold active">
                        <?= l('dashboard.browser_names.header') ?>
                    </a>

                    &bull;

                    <a href="#" id="browser_languages_link" class="font-weight-bold text-muted">
                        <?= l('dashboard.browser_languages.header') ?>
                    </a>

                    &bull;

                    <a href="#" id="browser_timezones_link" class="font-weight-bold text-muted">
                        <?= l('dashboard.browser_timezones.header') ?>
                    </a>
                </div>

                <div class="mt-4" id="browser_names_result" data-limit="7"></div>
                <div class="mt-4 d-none" id="browser_languages_result" data-limit="7"></div>
                <div class="mt-4 d-none" id="browser_timezones_result" data-limit="7"></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6 p-3">
        <div class="card border-0 position-relative">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <a href="<?= url($data->base_url_path . 'realtime') ?>" class="small text-decoration-none text-body text-uppercase font-weight-bold stretched-link"><?= l('realtime.menu') ?></a>
                    </div>

                    <span class="round-circle-sm bg-gray-200 text-primary-700 p-3">
                        <i class="fas fa-fw fa-sm fa-signal"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6 p-3">
        <div class="card border-0 position-relative">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <a href="<?= url($data->base_url_path . 'goals') ?>" class="small text-decoration-none text-body text-uppercase font-weight-bold stretched-link"><?= l('analytics.goals') ?></a>
                    </div>

                    <span class="round-circle-sm bg-gray-200 text-primary-700 p-3">
                        <i class="fas fa-fw fa-sm fa-bullseye"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require THEME_PATH . 'views/partials/js_chart_defaults.php' ?>

<?php ob_start() ?>
<?php if(count($data->logs)): ?>
<script>
    let css = window.getComputedStyle(document.body);
    let color = css.getPropertyValue('--primary');
    let color_gradient = null;

    /* Chart */
    let chart = document.getElementById('logs_chart').getContext('2d');

    /* Colors */
    color_gradient = chart.createLinearGradient(0, 0, 0, 250);
    color_gradient.addColorStop(0, set_hex_opacity(color, 0.6));
    color_gradient.addColorStop(1, set_hex_opacity(color, 0.1));

    new Chart(chart, {
        type: 'line',
        data: {
            labels: <?= $data->logs_chart['labels'] ?>,
            datasets: [
                {
                    data: <?= $data->logs_chart['pageviews'] ?? '[]' ?>,
                    backgroundColor: color_gradient,
                    borderColor: color,
                    fill: true,
                    label: <?= json_encode(l('dashboard.basic.chart.pageviews')) ?>
                },
                {
                    data: <?= $data->logs_chart['sessions'] ?? '[]' ?>,
                    backgroundColor: 'rgba(0,0,0,0)',
                    borderColor: 'rgba(0,0,0,0)',
                    fill: false,
                    showLine: false,
                    borderWidth: 0,
                    pointBorderWidth: 0,
                    pointBorderRadius: 0,
                    label: <?= json_encode(l('dashboard.basic.chart.sessions')) ?>
                },
                {
                    data: <?= $data->logs_chart['visitors'] ?? '[]' ?>,
                    backgroundColor: 'rgba(0,0,0,0)',
                    borderColor: 'rgba(0,0,0,0)',
                    fill: false,
                    showLine: false,
                    borderWidth: 0,
                    pointBorderWidth: 0,
                    pointBorderRadius: 0,
                    label: <?= json_encode(l('dashboard.basic.chart.visitors')) ?>
                }
            ]
        },
        options: chart_options,
    });
</script>
<?php endif ?>
<?php \Altum\Event::add_content(ob_get_clean(), 'javascript') ?>
