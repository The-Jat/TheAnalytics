<?php defined('ALTUMCODE') || die() ?>

<div class="altum-animate altum-animate-fill-none altum-animate-fade-in">
    <div class="d-flex justify-content-between mb-2">
        <div>
            <small class="text-muted font-weight-bold text-uppercase"><?= l('dashboard.referrers.referrer_host') ?></small>
        </div>

        <div class="d-flex justify-content-end">
            <?php if($data->options['bounce_rate']): ?>
                <div class="col">
                    <small class="text-muted font-weight-bold text-uppercase"><?= l('analytics.' . $data->by) ?></small>
                </div>

                <div class="col p-0 text-right">
                    <small class="text-muted font-weight-bold text-uppercase"><?= l('analytics.bounce_rate') ?></small>
                </div>
            <?php else: ?>
                <div class="col p-0">
                    <small class="text-muted font-weight-bold text-uppercase"><?= l('analytics.' . $data->by) ?></small>
                </div>
            <?php endif ?>
        </div>
    </div>

    <?php if(!$data->total_sum): ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between mb-1">
                <div>
                    <span class="text-muted"><?= l('dashboard.basic.no_data') ?></span>
                </div>

                <div class="d-flex justify-content-end">
                    <div class="col">-</div>

                    <div class="col p-0 text-right" style="min-width:50px;"><small class="text-muted">-</small></div>
                </div>
            </div>
        </div>
    <?php else: ?>

        <?php foreach($data->rows as $row): ?>
            <?php $percentage = round($row->total / $data->total_sum * 100, 1) ?>
            <?php $bounce_rate = !isset($row->bounces) || is_null($row->bounces) ? null : round($row->bounces / $row->total * 100, 1) ?>

            <div class="mb-3 row-fade-show-icon">
                <div class="d-flex justify-content-between mb-1">
                    <div class="text-truncate">
                        <img referrerpolicy="no-referrer" src="<?= get_favicon_url_from_domain($row->referrer) ?>" class="img-fluid icon-favicon mr-1" loading="lazy" />
                        <span class="">
                            <?php
                            switch($row->referrer){
                                case 'facebook.com': echo 'Facebook'; break;
                                case 'instagram.com': echo 'Instagram'; break;
                                case 'youtube.com': echo 'YouTube'; break;
                                case 'pinterest.com': echo 'Pinterest'; break;
                                case 'twitter.com': echo 'Twitter'; break;
                            }
                            ?>
                        </span>
                    </div>

                    <div class="d-flex justify-content-end">
                        <div class="col">
                            <span>
                                <?= nr($row->total) ?>
                            </span>
                        </div>

                        <div class="col p-0 text-right" style="min-width:50px;"><small class="text-muted"><?= $percentage ?>%</small></div>

                        <?php if($data->options['bounce_rate']): ?>
                            <div class="col p-0 text-right" style="min-width:70px;"><small class="text-muted"><?= !is_null($bounce_rate) ? $bounce_rate . '%' : '-' ?></small></div>
                        <?php endif ?>
                    </div>
                </div>

                <div class="progress" style="height: 5px;">
                    <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%;" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>

        <?php endforeach ?>

        <?php if($data->total_rows > count($data->rows)): ?>
            <a href="<?= url($this->base_url_path . 'referrers') ?>"><?= sprintf(l('global.view_x_more'), nr($data->total_rows - count($data->rows))) ?></a>
        <?php endif ?>

    <?php endif ?>
</div>
