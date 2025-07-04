<?php defined('ALTUMCODE') || die() ?>

<div class="modal fade" id="heatmap_update" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-body">
                <div class="d-flex justify-content-between mb-3">
                    <h5 class="modal-title">
                        <i class="fas fa-fw fa-sm fa-pen text-dark mr-2"></i>
                        <?= l('heatmap_update_modal.header') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" title="<?= l('global.close') ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <form name="heatmap_update" method="post" role="form">
                    <input type="hidden" name="token" value="<?= \Altum\Csrf::get() ?>" required="required" />
                    <input type="hidden" name="request_type" value="update" />
                    <input type="hidden" name="heatmap_id" value="" />

                    <div class="notification-container"></div>

                    <div class="form-group">
                        <label><i class="fas fa-fw fa-sm fa-signature text-gray-700 mr-1"></i> <?= l('global.name') ?></label>
                        <input type="text" class="form-control" name="name" required="required" />
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input
                                    type="checkbox"
                                    class="custom-control-input"
                                    name="is_enabled"
                                    id="heatmap_update_is_enabled"
                            >
                            <label class="custom-control-label" for="heatmap_update_is_enabled"><?= l('heatmap_update_modal.is_enabled') ?></label>
                            <small class="form-text text-muted"><?= l('heatmap_update_modal.is_enabled_help') ?></small>
                        </div>
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
    /* On modal show load new data */
    $('#heatmap_update').on('show.bs.modal', event => {
        let heatmap_id = $(event.relatedTarget).data('heatmap-id');
        let name = $(event.relatedTarget).data('name');
        let is_enabled = $(event.relatedTarget).data('is-enabled');

        $(event.currentTarget).find('input[name="heatmap_id"]').val(heatmap_id);
        $(event.currentTarget).find('input[name="name"]').val(name);
        $(event.currentTarget).find('input[name="is_enabled"]').prop('checked', is_enabled);
    });


    $('form[name="heatmap_update"]').on('submit', event => {
        let notification_container = event.currentTarget.querySelector('.notification-container');
        notification_container.innerHTML = '';
        pause_submit_button(event.currentTarget.querySelector('[type="submit"][name="submit"]'));

        $.ajax({
            type: 'POST',
            url: `${url}heatmaps-ajax/update`,
            data: $(event.currentTarget).serialize(),
            dataType: 'json',
            success: (data) => {
                enable_submit_button(event.currentTarget.querySelector('[type="submit"][name="submit"]'));

                if(data.status == 'error') {
                    display_notifications(data.message, 'error', notification_container);
                }

                else if(data.status == 'success') {

                    display_notifications(data.message, 'success', notification_container);

                    setTimeout(() => {

                        /* Hide modal */
                        $('#heatmap_update').modal('hide');

                        /* Clear input values */
                        $('form[name="heatmap_update"] input').val('');

                        /* Fade out refresh */
                        redirect('heatmaps');

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
