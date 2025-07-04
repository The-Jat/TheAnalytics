<?php defined('ALTUMCODE') || die() ?>

<div class="container">
    <?= \Altum\Alerts::output_alerts() ?>

    <?php if(!in_array($data->type, ['default', 'goals'])): ?>
        <?php if(settings()->main->breadcrumbs_is_enabled): ?>
            <nav aria-label="breadcrumb">
                <ol class="custom-breadcrumbs small">
                    <li><a href="<?= url($data->base_url_path) ?>"><?= l(\Altum\Router::$controller_key . '.breadcrumb') ?></a> <i class="fas fa-fw fa-angle-right"></i></li>
                    <li class="active" aria-current="page"><?= l('dashboard.' . $data->type . '.header') ?></li>
                </ol>
            </nav>
        <?php endif ?>
    <?php endif ?>

    <div class="row mb-4">
        <?php if($data->type == 'goals'): ?>
        <div class="col-12 col-lg d-flex align-items-center mb-3 mb-lg-0 text-truncate">
            <h1 class="h4 m-0 text-truncate"><i class="fas fa-fw fa-xs fa-bullseye mr-1"></i> <?= l('goals.header') ?></h1>

            <div class="ml-2">
                <span data-toggle="tooltip" title="<?= l('goals.subheader') ?>">
                    <i class="fas fa-fw fa-info-circle text-muted"></i>
                </span>
            </div>
        </div>
        <?php else: ?>
        <div class="col-12 col-lg d-flex align-items-center mb-3 mb-lg-0 text-truncate">
            <h1 class="h4 m-0 text-truncate"><?= $this->website->host . $this->website->path ?></h1>
        </div>
        <?php endif ?>

        <div class="col-12 col-lg-auto d-flex flex-wrap gap-3 d-print-none">
            <?php if($data->type == 'default' && \Altum\Router::$controller_key == 'dashboard' && !$this->team): ?>
                <div class="mr-3">
                    <button
                            type="button"
                            class="btn btn-sm btn-link text-secondary"
                            data-toggle="modal"
                            data-target="#website_statistics_reset_modal"
                            aria-label="<?= l('statistics_reset_modal.header') ?>"
                            data-website-id="<?= $this->website->website_id ?>"
                            data-start-date="<?= $data->datetime['start_date'] ?>"
                            data-end-date="<?= $data->datetime['end_date'] ?>"
                            data-tooltip
                            title="<?= l('statistics_reset_modal.header') ?>"
                            data-tooltip-hide-on-click
                    >
                        <i class="fas fa-fw fa-sm fa-eraser"></i>
                    </button>
                </div>
            <?php endif ?>

            <div>
                <button
                        id="daterangepicker"
                        type="button"
                        class="btn btn-sm btn-light"
                        data-min-date="<?= \Altum\Date::get($this->website->datetime, 4) ?>"
                        data-max-date="<?= \Altum\Date::get('', 4) ?>"
                >
                    <i class="fas fa-fw fa-calendar mr-lg-1"></i>
                    <span class="d-none d-lg-inline-block">
                        <?php if($data->datetime['start_date'] == $data->datetime['end_date']): ?>
                            <?= \Altum\Date::get($data->datetime['start_date'], 6, \Altum\Date::$default_timezone) ?>
                        <?php else: ?>
                            <?= \Altum\Date::get($data->datetime['start_date'], 6, \Altum\Date::$default_timezone) . ' - ' . \Altum\Date::get($data->datetime['end_date'], 6, \Altum\Date::$default_timezone) ?>
                        <?php endif ?>
                    </span>
                    <i class="fas fa-fw fa-caret-down d-none d-lg-inline-block ml-lg-1"></i>
                </button>
            </div>

            <?php if($data->type == 'default' && \Altum\Router::$controller_key == 'dashboard'): ?>
                <?php // do not allow export for public pages ?>
                <div>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-light dropdown-toggle-simple <?= $data->has_logs ? null : 'disabled' ?>" data-toggle="dropdown" data-boundary="viewport" data-tooltip title="<?= l('global.export') ?>" data-tooltip-hide-on-click>
                            <i class="fas fa-fw fa-sm fa-download"></i>
                        </button>

                        <div class="dropdown-menu dropdown-menu-right d-print-none">
                            <a href="<?= url('dashboard/export_' . $this->website->tracking_type . '/csv')  ?>" target="_blank" class="dropdown-item <?= $this->user->plan_settings->export->csv ? null : 'disabled' ?>">
                                <i class="fas fa-fw fa-sm fa-file-csv mr-2"></i> <?= sprintf(l('global.export_to'), 'CSV') ?>
                            </a>
                            <a href="<?= url('dashboard/export_' . $this->website->tracking_type . '/json') ?>" target="_blank" class="dropdown-item <?= $this->user->plan_settings->export->json ? null : 'disabled' ?>">
                                <i class="fas fa-fw fa-sm fa-file-code mr-2"></i> <?= sprintf(l('global.export_to'), 'JSON') ?>
                            </a>
                            <button type="button" class="dropdown-item <?= $this->user->plan_settings->export->pdf ? null : 'disabled' ?>" onclick="window.print()">
                                <i class="fas fa-fw fa-file-pdf mr-2"></i> <?= sprintf(l('global.export_to'), 'PDF') ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif ?>

            <div>
                <button type="button" class="btn btn-sm btn-light <?= $data->has_logs ? null : 'disabled' ?>" <?= $data->has_logs ? 'onclick="$(\'#filters\').toggle();"' : null ?> data-toggle="tooltip" title="<?= l('analytics.filters.toggle') ?>" data-tooltip-hide-on-click>
                    <i class="fas fa-fw fa-filter"></i>
                </button>
            </div>
        </div>
    </div>

    <?php if($data->type == 'default'): ?>

        <?php if($this->website->tracking_type == 'normal'): ?>
            <div class="row mb-4">
                <div class="col-12 col-lg-4 mb-3 mb-lg-0">
                    <div class="card border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex flex-column">
                                    <small class="text-muted text-uppercase font-weight-bold"><?= l('analytics.pageviews') ?></small>
                                    <span class="h4 font-weight-bolder"><?= nr($data->basic_totals['pageviews']) ?></span>
                                </div>

                                <span class="round-circle-md bg-gray-200 text-primary-700 p-3">
                                    <i class="fas fa-fw fa-lg fa-eye"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4 mb-3 mb-lg-0">
                    <div class="card border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex flex-column">
                                    <small class="text-muted text-uppercase font-weight-bold"><?= l('analytics.sessions') ?></small>
                                    <span class="h4 font-weight-bolder"><?= nr($data->basic_totals['sessions']) ?></span>
                                </div>

                                <span class="round-circle-md bg-gray-200 text-primary-700 p-3">
                                        <i class="fas fa-fw fa-lg fa-hourglass-half"></i>
                                    </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4 mb-3 mb-lg-0">
                    <div class="card border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex flex-column">
                                    <small class="text-muted text-uppercase font-weight-bold"><?= l('analytics.visitors') ?></small>
                                    <span class="h4 font-weight-bolder"><?= nr($data->basic_totals['visitors']) ?></span>
                                </div>

                                <span class="round-circle-md bg-gray-200 text-primary-700 p-3">
                                    <i class="fas fa-fw fa-lg fa-users"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <?php if($this->website->tracking_type == 'lightweight'): ?>
            <div class="row mb-4">
                <div class="col-12 col-lg-6 mb-3 mb-lg-0">
                    <div class="card border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex flex-column">
                                    <small class="text-muted text-uppercase font-weight-bold"><?= l('analytics.pageviews') ?></small>
                                    <span class="h4 font-weight-bolder"><?= nr($data->basic_totals['pageviews']) ?></span>
                                </div>

                                <span class="round-circle-md bg-gray-200 text-primary-700 p-3">
                                    <i class="fas fa-fw fa-lg fa-eye"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6 mb-3 mb-lg-0">
                    <div class="card border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex flex-column">
                                    <small class="text-muted text-uppercase font-weight-bold"><?= l('analytics.visitors') ?></small>
                                    <span class="h4 font-weight-bolder"><?= nr($data->basic_totals['visitors']) ?></span>
                                </div>

                                <span class="round-circle-md bg-gray-200 text-primary-700 p-3">
                                    <i class="fas fa-fw fa-lg fa-users"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif ?>

    <?php endif ?>

        <?= (new \Altum\View('partials/analytics/filters_wrapper', (array) $this))->run(['available_filters' => null, 'tracking_type' => $this->website->tracking_type]) ?>

    <?php if(!$data->has_logs): ?>

        <?= include_view(THEME_PATH . 'views/partials/no_data.php', [
            'filters_get' => isset($_COOKIE['filters']) ? json_decode($_COOKIE['filters']) : [],
            'name' => 'dashboard.basic',
            'has_secondary_text' => true,
            'has_clear_filters_button' => false,
        ]); ?>

    <?php else: ?>

        <?= $this->views['dashboard_content'] ?>

    <?php endif ?>

</div>

<input type="hidden" name="start_date" value="<?= \Altum\Date::get($data->datetime['start_date'], 4, \Altum\Date::$default_timezone) ?>" />
<input type="hidden" name="end_date" value="<?= \Altum\Date::get($data->datetime['end_date'], 4, \Altum\Date::$default_timezone) ?>" />
<input type="hidden" name="website_id" value="<?= $this->website->website_id ?>" />
<input type="hidden" name="pixel_key" value="<?= $this->website->pixel_key ?>" />

<?php ob_start() ?>
<link href="<?= ASSETS_FULL_URL . 'css/libraries/daterangepicker.min.css?v=' . PRODUCT_CODE ?>" rel="stylesheet" media="screen,print">
<?php \Altum\Event::add_content(ob_get_clean(), 'head') ?>

<?php ob_start() ?>
<script src="<?= ASSETS_FULL_URL . 'js/libraries/moment.min.js?v=' . PRODUCT_CODE ?>"></script>
<script src="<?= ASSETS_FULL_URL . 'js/libraries/daterangepicker.min.js?v=' . PRODUCT_CODE ?>"></script>
<script src="<?= ASSETS_FULL_URL . 'js/libraries/moment-timezone-with-data-10-year-range.min.js?v=' . PRODUCT_CODE ?>"></script>

<script>
    'use strict';

    moment.tz.setDefault(<?= json_encode($this->user->timezone ?? \Altum\Date::$default_timezone) ?>);

    /* Daterangepicker */
    $('#daterangepicker').daterangepicker({
        startDate: <?= json_encode($data->datetime['start_date']) ?>,
        endDate: <?= json_encode($data->datetime['end_date']) ?>,
        minDate: $('#daterangepicker').data('min-date'),
        maxDate: $('#daterangepicker').data('max-date'),
        ranges: {
            <?= json_encode(l('global.date.today')) ?>: [moment(), moment()],
            <?= json_encode(l('global.date.yesterday')) ?>: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            <?= json_encode(l('global.date.last_7_days')) ?>: [moment().subtract(6, 'days'), moment()],
            <?= json_encode(l('global.date.last_30_days')) ?>: [moment().subtract(29, 'days'), moment()],
            <?= json_encode(l('global.date.this_month')) ?>: [moment().startOf('month'), moment().endOf('month')],
            <?= json_encode(l('global.date.last_month')) ?>: [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            <?= json_encode(l('global.date.all_time')) ?>: [moment($('#daterangepicker').data('min-date')), moment()]
        },
        alwaysShowCalendars: true,
        linkedCalendars: false,
        singleCalendar: true,
        locale: <?= json_encode(require APP_PATH . 'includes/daterangepicker_translations.php') ?>,
    }, (start, end, label) => {

        /* Redirect */
        redirect(`<?= url($data->base_url_path . ($data->type == 'default' ? null : $data->type)) ?>?start_date=${start.format('YYYY-MM-DD')}&end_date=${end.format('YYYY-MM-DD')}`, true);

    });

    <?php if($data->has_logs): ?>

    /* Basic data to use for fetching extra data */
    let tracking_type = <?= json_encode($this->website->tracking_type) ?>;
    let website_id = $('input[name="website_id"]').val();
    let pixel_key = $('input[name="pixel_key"]').val();
    let start_date = $('input[name="start_date"]').val();
    let end_date = $('input[name="end_date"]').val();

    /* Dynamic widget changing processing */
    let widgets_icons = {
        'paths': 'fas fa-copy',
        'landing_paths': 'fas fa-plane-arrival',
        'exit_paths': 'fas fa-sign-out-alt',

        'referrers': 'fas fa-random',
        'utms_source': 'fas fa-link',

        'continents': 'fas fa-globe-europe',
        'countries': 'fas fa-flag',
        'cities': 'fas fa-city',

        'device_types': 'fas fa-laptop',
        'screen_resolutions': 'fas fa-desktop',
        'themes': 'fas fa-moon',

        'browser_names': 'fas fa-window-restore',
        'browser_languages': 'fas fa-language',
        'browser_timezones': 'fas fa-user-clock',
    };

    let pages_array = ['paths', 'landing_paths', 'exit_paths'];
    let sources_array = ['referrers', 'utms_source'];
    let locations_array = ['continents', 'countries', 'cities'];
    let devices_array = ['device_types', 'screen_resolutions', 'themes'];
    let browsers_array = ['browser_names', 'browser_languages', 'browser_timezones'];

    let initiate_widget_selector = (widgets_array, icon_selector) => {
        widgets_array.forEach(type => {
            document.querySelector(`#${type}_link`) && document.querySelector(`#${type}_link`).addEventListener('click', event => {
                event.preventDefault();

                /* Skip if already active */
                if(event.currentTarget.classList.contains('active')) {
                    return;
                }

                let other_widgets_array = widgets_array.filter(item => item !== type);

                /* Hide others */
                other_widgets_array.forEach(key => {
                    /* Hide ajax results */
                    document.querySelector(`#${key}_result`).classList.add('d-none');

                    /* Deselect active links */
                    document.querySelector(`#${key}_link`).classList.remove('active');
                    document.querySelector(`#${key}_link`).classList.add('text-muted');

                    /* Hide potential view more links */
                    document.querySelector(`#${key}_view_more`) && document.querySelector(`#${key}_view_more`).classList.add('d-none');
                })

                /* Show new selected */
                document.querySelector(`#${type}_result`).classList.remove('d-none');

                /* Select active link */
                document.querySelector(`#${type}_link`).classList.add('active');
                document.querySelector(`#${type}_link`).classList.remove('text-muted');

                /* Show potential view more link */
                document.querySelector(`#${type}_view_more`) && document.querySelector(`#${type}_view_more`).classList.remove('d-none');

                /* Icon change */
                document.querySelector(icon_selector).innerHTML = `<i class="${widgets_icons[type]} fa-fw fa-sm"></i>`;

                /* Skip if already requested and has data */
                if(document.querySelector(`#${type}_result`).innerHTML.length) {
                    return;
                }

                /* Send request and display */
                get_and_output_data(type);
            })
        })
    }

    initiate_widget_selector(pages_array, '#all_pages_icon');
    initiate_widget_selector(sources_array, '#sources_icon');
    initiate_widget_selector(locations_array, '#locations_icon');
    initiate_widget_selector(devices_array, '#devices_icon');
    initiate_widget_selector(browsers_array, '#browsers_icon');

    /* Get ajaxed data function */
    let get_and_output_data = (request_type) => {
        let limit = $(`#${request_type}_result`).data('limit') || 10;
        let bounce_rate = $(`#${request_type}_result`).data('bounce-rate') || false;
        let source = <?= json_encode(\Altum\Router::$controller_key) ?>;

        /* Put the loading placeholders */
        $(`#${request_type}_result`).html($('#loading').html());

        /* Send the request */
        (async () => {
            let url_query = build_url_query({
                website_id,
                pixel_key,
                start_date,
                end_date,
                global_token,
                request_type,
                limit,
                bounce_rate,
                source,
            });

            await fetch(`${url}statistics-ajax-${tracking_type}?${url_query}`)
                .then(response => {
                    if(response.ok) {
                        return response.json();
                    } else {
                        return Promise.reject(response);
                    }
                })
                .then(data => {

                    $(`#${request_type}_result`).html(data.details.html);

                    /* Send data to the countries map if needed */
                    if(request_type == 'countries' && $('#countries_map').length) {
                        $('#countries_map').trigger('load', data.details.data);
                    }

                })
                .catch(error => {

                    $(`#${request_type}_result`).html(<?= json_encode(l('global.error_message.basic')) ?>)

                });

            tooltips_initiate();
        })();
    }

    for(let request_type of ['paths', 'landing_paths', 'exit_paths', 'referrers', 'social_media_referrers', 'search_engines_referrers', 'continents', 'countries', 'cities', 'utms_source', 'screen_resolutions', 'themes', 'browser_languages', 'operating_systems', 'device_types', 'browser_names', 'goals']) {
        if(document.querySelector(`#${request_type}_result`) && !document.querySelector(`#${request_type}_result`).classList.contains('d-none')) {
            get_and_output_data(request_type);
        }
    }

    <?php endif ?>
</script>
<?php \Altum\Event::add_content(ob_get_clean(), 'javascript') ?>

<?php if(\Altum\Router::$controller_key == 'dashboard'): ?>
    <?php \Altum\Event::add_content(include_view(THEME_PATH . 'views/partials/statistics_reset_modal.php', ['modal_id' => 'website_statistics_reset_modal', 'resource_id' => 'website_id', 'path' => 'dashboard/reset']), 'modals'); ?>
<?php endif ?>
