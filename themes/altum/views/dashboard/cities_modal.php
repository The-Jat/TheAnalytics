<?php defined('ALTUMCODE') || die() ?>

<div class="modal fade" id="cities_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="p-3">
                <div class="d-flex justify-content-between">
                    <h5 class="modal-title"><?= l('global.cities') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" title="<?= l('global.close') ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>

            <div class="modal-body">
                <div class="notification-container"></div>

                <div id="cities_modal_result"></div>
            </div>

        </div>
    </div>
</div>

<?php ob_start() ?>
<script>
    /* On modal show load new data */
    $('#cities_modal').on('show.bs.modal', event => {
        let loading_html = $('#loading').html();

        /* Basic data to use for fetching extra data */
        let website_id = $('input[name="website_id"]').val();
        let pixel_key = $('input[name="pixel_key"]').val();
        let tracking_type = <?= json_encode($this->website->tracking_type) ?>;
        let start_date = $('input[name="start_date"]').val();
        let end_date = $('input[name="end_date"]').val();
        let country_code = $(event.relatedTarget).data('country-code');
        let source = <?= json_encode(\Altum\Router::$controller_key) ?>;

        /* Place the loading html */
        $('#cities_modal_result').html(loading_html);

        /* Build the query */
        let url_query = build_url_query({
            website_id,
            pixel_key,
            start_date,
            end_date,
            global_token,
            request_type: 'cities',
            limit: -1,
            bounce_rate: true,
            country_code,
            source,
        });

        $.ajax({
            type: 'GET',
            url: `${url}statistics-ajax-${tracking_type}?${url_query}`,
            success: (data) => {
                let notification_container = event.currentTarget.querySelector('.notification-container');
                notification_container.innerHTML = '';

                if(data.status == 'error') {
                    display_notifications(data.message, 'error', notification_container);
                }

                else if(data.status == 'success') {
                    $('#cities_modal_result').html(data.details.html);
                }

                tooltips_initiate();
            },
            dataType: 'json'
        });

    });
</script>
<?php \Altum\Event::add_content(ob_get_clean(), 'javascript') ?>
