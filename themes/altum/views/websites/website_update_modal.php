<?php defined('ALTUMCODE') || die() ?>

<div class="modal fade" id="website_update_modal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-body">
                <div class="d-flex justify-content-between mb-3">
                    <h5 class="modal-title">
                        <i class="fas fa-fw fa-sm fa-pen text-dark mr-2"></i>
                        <?= l('website_update_modal.header') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" title="<?= l('global.close') ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <form name="website_update" method="post" role="form">
                    <input type="hidden" name="token" value="<?= \Altum\Csrf::get() ?>" required="required" />
                    <input type="hidden" name="request_type" value="update" />
                    <input type="hidden" name="website_id" value="" />

                    <div class="notification-container"></div>

                    <div class="form-group">
                        <label for="website_update_name"><i class="fas fa-fw fa-sm fa-signature text-muted mr-1"></i> <?= l('global.name') ?></label>
                        <input id="website_update_name" type="text" class="form-control" name="name" />
                    </div>

                    <div class="form-group">
                        <label for="website_update_host"><i class="fas fa-fw fa-sm fa-pager text-muted mr-1"></i> <?= l('websites.host') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <select name="scheme" class="appearance-none custom-select custom-select-lg form-control input-group-text">
                                    <option value="https://">https://</option>
                                    <option value="http://">http://</option>
                                </select>
                            </div>

                            <input id="website_update_host" type="text" class="form-control" name="host" placeholder="<?= l('global.host_placeholder') ?>" required="required" />
                        </div>
                        <small class="form-text text-muted"><?= l('websites.host_help') ?></small>
                    </div>

                    <?php if(count($data->domains) && settings()->analytics->domains_is_enabled): ?>
                    <div class="form-group">
                        <label for="website_update_domain_id"><i class="fas fa-fw fa-sm fa-globe text-muted mr-1"></i> <?= l('websites.domain_id') ?></label>
                        <select id="website_update_domain_id" name="domain_id" class="custom-select">
                            <option value=""><?= parse_url(SITE_URL, PHP_URL_HOST) ?></option>
                            <?php foreach($data->domains as $row): ?>
                                <option value="<?= $row->domain_id ?>"><?= $row->host ?></option>
                            <?php endforeach ?>
                        </select>
                        <small class="form-text text-muted"><?= l('websites.domain_id_help') ?></small>
                    </div>
                    <?php endif ?>

                    <div class="form-group">
                        <label for="website_update_tracking_type"><i class="fas fa-fw fa-sm fa-chart-bar text-muted mr-1"></i> <?= l('websites.tracking_type') ?></label>
                        <select id="website_update_tracking_type" name="tracking_type" class="custom-select form-control-lg" disabled="disabled">
                            <option value="lightweight">🪶 <?= l('websites.tracking_type_lightweight') ?></option>
                            <option value="normal">🧠 <?= l('websites.tracking_type_normal') ?></option>
                        </select>
                        <small data-tracking-type="lightweight" class="form-text text-muted d-none"><?= l('websites.tracking_type_lightweight_help') ?></small>
                        <small data-tracking-type="normal" class="form-text text-muted d-none"><?= l('websites.tracking_type_normal_help') ?></small>
                    </div>

                    <div class="form-group custom-control custom-switch">
                        <input
                                type="checkbox"
                                class="custom-control-input"
                                name="is_enabled"
                                id="website_update_is_enabled"
                        >
                        <label class="custom-control-label" for="website_update_is_enabled"><?= l('websites.is_enabled') ?></label>
                        <small class="form-text text-muted"><?= l('websites.is_enabled_help') ?></small>
                    </div>

                    <div data-tracking-type="normal" class="d-none">
                        <div <?= $this->user->plan_settings->events_children_limit ? null : get_plan_feature_disabled_info() ?>>
                            <div class="form-group custom-control custom-switch <?= $this->user->plan_settings->events_children_limit ? null : 'container-disabled' ?>">
                                <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        name="events_children_is_enabled"
                                        id="website_update_events_children_is_enabled"
                                        <?= $this->user->plan_settings->events_children_limit ? null : 'disabled="disabled"' ?>
                                >
                                <label class="custom-control-label" for="website_update_events_children_is_enabled"><?= l('websites.events_children_is_enabled') ?></label>
                                <small class="form-text text-muted"><?= l('websites.events_children_is_enabled_help') ?></small>
                            </div>
                        </div>

                        <?php if(settings()->analytics->sessions_replays_is_enabled): ?>
                        <div <?= $this->user->plan_settings->sessions_replays_limit ? null : get_plan_feature_disabled_info() ?>>
                            <div class="form-group custom-control custom-switch <?= $this->user->plan_settings->sessions_replays_limit ? null : 'container-disabled' ?>">
                                <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        name="sessions_replays_is_enabled"
                                        id="website_update_sessions_replays_is_enabled"
                                        <?= $this->user->plan_settings->sessions_replays_limit ? null : 'disabled="disabled"' ?>
                                />
                                <label class="custom-control-label" for="website_update_sessions_replays_is_enabled"><?= l('websites.sessions_replays_is_enabled') ?></label>
                                <small class="form-text text-muted"><?= l('websites.sessions_replays_is_enabled_help') ?></small>
                            </div>
                        </div>
                        <?php endif ?>
                    </div>

                    <?php if(settings()->analytics->email_reports_is_enabled): ?>
                        <div <?= $this->user->plan_settings->email_reports_is_enabled ? null : get_plan_feature_disabled_info() ?>>
                            <div class="form-group custom-control custom-switch <?= $this->user->plan_settings->email_reports_is_enabled ? null : 'container-disabled' ?>">
                            <input
                                    type="checkbox"
                                    class="custom-control-input"
                                    name="email_reports_is_enabled"
                                    id="website_update_email_reports_is_enabled"
                                    <?= $this->user->plan_settings->email_reports_is_enabled ? null : 'disabled="disabled"' ?>
                            >
                            <label class="custom-control-label" for="website_update_email_reports_is_enabled"><?= l('global.plan_settings.email_reports_is_enabled_' . settings()->analytics->email_reports_is_enabled) ?></label>
                            <small class="form-text text-muted"><?= l('websites.email_reports_is_enabled_help') ?></small>
                        </div>
                    </div>
                    <?php endif ?>

                    <div class="form-group custom-control custom-switch">
                        <input
                                type="checkbox"
                                class="custom-control-input"
                                name="bot_exclusion_is_enabled"
                                id="website_update_bot_exclusion_is_enabled"
                        >
                        <label class="custom-control-label" for="website_update_bot_exclusion_is_enabled"><?= l('websites.bot_exclusion_is_enabled') ?></label>
                        <small class="form-text text-muted"><?= l('websites.bot_exclusion_is_enabled_help') ?></small>
                    </div>

                    <div class="form-group custom-control custom-switch">
                        <input
                                type="checkbox"
                                class="custom-control-input"
                                name="query_parameters_tracking_is_enabled"
                                id="website_update_query_parameters_tracking_is_enabled"
                        >
                        <label class="custom-control-label" for="website_update_query_parameters_tracking_is_enabled"><?= l('websites.query_parameters_tracking_is_enabled') ?></label>
                        <small class="form-text text-muted"><?= l('websites.query_parameters_tracking_is_enabled_help') ?></small>
                    </div>

                    <?php if(settings()->analytics->ip_storage_is_enabled): ?>
                    <div class="form-group custom-control custom-switch" data-tracking-type="normal">
                        <input
                                type="checkbox"
                                class="custom-control-input"
                                name="ip_storage_is_enabled"
                                id="website_update_ip_storage_is_enabled"
                        >
                        <label class="custom-control-label" for="website_update_ip_storage_is_enabled"><?= l('websites.ip_storage_is_enabled') ?></label>
                        <small class="form-text text-muted"><?= l('websites.ip_storage_is_enabled_help') ?></small>
                    </div>
                    <?php endif ?>

                    <div class="form-group custom-control custom-switch">
                        <input
                                type="checkbox"
                                class="custom-control-input"
                                name="public_statistics_is_enabled"
                                id="website_update_public_statistics_is_enabled"
                        >
                        <label class="custom-control-label" for="website_update_public_statistics_is_enabled"><?= l('websites.public_statistics_is_enabled') ?></label>
                        <small class="form-text text-muted"><?= l('websites.public_statistics_is_enabled_help') ?></small>
                    </div>

                    <div class="form-group" data-password-toggle-view data-password-toggle-view-show="<?= l('global.show') ?>" data-password-toggle-view-hide="<?= l('global.hide') ?>" data-public-statistics-is-enabled-type="on">
                        <label for="website_update_public_statistics_password"><i class="fas fa-fw fa-sm fa-lock text-muted mr-1"></i> <?= l('websites.public_statistics_password') ?></label>
                        <input id="website_update_public_statistics_password" type="password" class="form-control" name="public_statistics_password" />
                        <small class="form-text text-muted"><?= l('websites.public_statistics_password_help') ?></small>
                    </div>

                    <div class="form-group">
                        <label for="website_update_excluded_ips"><i class="fas fa-fw fa-sm fa-eye-slash text-muted mr-1"></i> <?= l('websites.excluded_ips') ?></label>
                        <textarea id="website_update_excluded_ips" class="form-control" name="excluded_ips"></textarea>
                        <small class="form-text text-muted"><?= l('websites.excluded_ips_help') ?></small>
                    </div>

                    <div class="mt-4">
                        <button type="submit" name="submit" class="btn btn-block btn-primary" data-is-ajax><?= l('global.update') ?></button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php ob_start() ?>
<script>
    type_handler('[name="public_statistics_is_enabled"]', 'data-public-statistics-is-enabled-type');
    document.querySelector('[name="public_statistics_is_enabled"]') && document.querySelectorAll('[name="public_statistics_is_enabled"]').forEach(element => element.addEventListener('change', () => { type_handler('[name="public_statistics_is_enabled"]', 'data-public-statistics-is-enabled-type'); }));

    /* On modal show load new data */
    $('#website_update_modal').on('show.bs.modal', event => {
        let website_id = $(event.relatedTarget).data('website-id');
        let domain_id = $(event.relatedTarget).data('domain-id');
        let name = $(event.relatedTarget).data('name');
        let scheme = $(event.relatedTarget).data('scheme');
        let host = $(event.relatedTarget).data('host');
        let tracking_type = $(event.relatedTarget).data('tracking-type');
        let is_enabled = $(event.relatedTarget).data('is-enabled');
        let events_children_is_enabled = $(event.relatedTarget).data('events-children-is-enabled');
        let sessions_replays_is_enabled = $(event.relatedTarget).data('sessions-replays-is-enabled');
        let email_reports_is_enabled = $(event.relatedTarget).data('email-reports-is-enabled');
        let excluded_ips = $(event.relatedTarget).data('excluded-ips');
        let public_statistics_password = $(event.relatedTarget).data('public-statistics-password');
        let bot_exclusion_is_enabled = $(event.relatedTarget).data('bot-exclusion-is-enabled');
        let query_parameters_tracking_is_enabled = $(event.relatedTarget).data('query-parameters-tracking-is-enabled');
        let ip_storage_is_enabled = $(event.relatedTarget).data('ip-storage-is-enabled');
        let public_statistics_is_enabled = $(event.relatedTarget).data('public-statistics-is-enabled');

        $(event.currentTarget).find('input[name="website_id"]').val(website_id);
        $(event.currentTarget).find('select[name="domain_id"]').val(domain_id);
        $(event.currentTarget).find('input[name="name"]').val(name);
        $(event.currentTarget).find('select[name="scheme"]').val(scheme);
        $(event.currentTarget).find('input[name="host"]').val(host).trigger('change');
        $(event.currentTarget).find('input[name="events_children_is_enabled"]').prop('checked', events_children_is_enabled);
        $(event.currentTarget).find('input[name="sessions_replays_is_enabled"]').prop('checked', sessions_replays_is_enabled);
        $(event.currentTarget).find('input[name="is_enabled"]').prop('checked', is_enabled);
        $(event.currentTarget).find('input[name="email_reports_is_enabled"]').prop('checked', email_reports_is_enabled);
        $(event.currentTarget).find('input[name="bot_exclusion_is_enabled"]').prop('checked', bot_exclusion_is_enabled);
        $(event.currentTarget).find('input[name="query_parameters_tracking_is_enabled"]').prop('checked', query_parameters_tracking_is_enabled);
        $(event.currentTarget).find('input[name="ip_storage_is_enabled"]').prop('checked', ip_storage_is_enabled);
        $(event.currentTarget).find('input[name="public_statistics_is_enabled"]').prop('checked', public_statistics_is_enabled);
        event.currentTarget.querySelector('input[name="public_statistics_is_enabled"]').dispatchEvent(new Event('change'));
        $(event.currentTarget).find('input[name="public_statistics_password"]').val(public_statistics_password);
        $(event.currentTarget).find('textarea[name="excluded_ips"]').val(excluded_ips);

        $(event.currentTarget).find('select').trigger('change');

        switch(tracking_type) {
            case 'lightweight':

                document.querySelectorAll('#website_update_modal [data-tracking-type="lightweight"]').forEach(element => {
                    element.classList.remove('d-none');
                });

                document.querySelectorAll('#website_update_modal [data-tracking-type="normal"]').forEach(element => {
                    element.classList.add('d-none');
                });

                document.querySelector('#website_update_modal select[name="tracking_type"] option[value="normal"]').removeAttribute('selected');
                document.querySelector('#website_update_modal select[name="tracking_type"] option[value="lightweight"]').selected = 'selected';

                break;

            case 'normal':

                document.querySelectorAll('#website_update_modal [data-tracking-type="lightweight"]').forEach(element => {
                    element.classList.add('d-none');
                });

                document.querySelectorAll('#website_update_modal [data-tracking-type="normal"]').forEach(element => {
                    element.classList.remove('d-none');
                });

                document.querySelector('#website_update_modal select[name="tracking_type"] option[value="lightweight"]').removeAttribute('selected');
                document.querySelector('#website_update_modal select[name="tracking_type"] option[value="normal"]').selected = 'selected';

                break;
        }

        $('#website_update_modal select[name="tracking_type"]').trigger('change');
    });


    $('form[name="website_update"]').on('submit', event => {
        let notification_container = event.currentTarget.querySelector('.notification-container');
        notification_container.innerHTML = '';
        pause_submit_button(event.currentTarget.querySelector('[type="submit"][name="submit"]'));

        $.ajax({
            type: 'POST',
            url: `${url}websites-ajax`,
            data: $(event.currentTarget).serialize(),
            dataType: 'json',
            success: (data) => {
                enable_submit_button(event.currentTarget.querySelector('[type="submit"][name="submit"]'));

                if(data.status == 'error') {
                    display_notifications(data.message, 'error', notification_container);

                    /* Auto scroll to notification */
                    notification_container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                else if(data.status == 'success') {

                    display_notifications(data.message, 'success', notification_container);

                    /* Auto scroll to notification */
                    notification_container.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    setTimeout(() => {

                        /* Hide modal */
                        $('#website_update_modal').modal('hide');

                        /* Clear input values */
                        $('form[name="website_update"] input').val('');

                        /* Refresh */
                        redirect('websites');

                        /* Remove the notification */
                        notification_container.innerHTML = '';

                    }, 1000);

                }
            },
            error: () => {
                enable_submit_button(event.currentTarget.querySelector('[type="submit"][name="submit"]'));
                display_notifications(<?= json_encode(l('global.error_message.basic')) ?>, 'error', notification_container);
            },
        });

        event.preventDefault();
    })
</script>
<?php \Altum\Event::add_content(ob_get_clean(), 'javascript') ?>
