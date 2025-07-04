<?php defined('ALTUMCODE') || die() ?>

<div class="altum-animate altum-animate-fill-none altum-animate-fade-in">
    <div class="d-flex justify-content-between mb-2">
        <div>
            <small class="text-muted font-weight-bold text-uppercase mr-3"><?= l('dashboard.goals.goal') ?></small>
        </div>

        <div>
            <small class="text-muted font-weight-bold text-uppercase"><?= l('analytics.' . $data->by) ?></small>
        </div>
    </div>

    <?php if(!$data->total_rows): ?>
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
            <?php $percentage = $data->total_sum > 0 ? round($row->total / $data->total_sum * 100, 1) : 0 ?>

            <div class="mb-3 row-fade-show-icon">
                <div class="d-flex justify-content-between mb-1">
                    <div>
                        <a href="#" data-toggle="modal" data-target="#goal_update_modal" data-goal-id="<?= $row->goal_id ?>" data-key="<?= $row->key ?>" data-type="<?= $row->type ?>" data-path="<?= ltrim($row->path, '/') ?>" data-name="<?= $row->name ?>"><?= $row->name ?></a>
                    </div>

                    <div class="d-flex justify-content-end">
                        <div class="col"><?= nr($row->total) ?></div>

                        <div class="col p-0 text-right" style="min-width:50px;"><small class="text-muted"><?= $percentage ?>%</small></div>
                    </div>
                </div>

                <div class="progress" style="height: 5px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $percentage ?>%;" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>

        <?php endforeach ?>

        <?php if($data->total_rows > count($data->rows)): ?>
            <a href="<?= url($this->base_url_path . 'goals') ?>"><?= sprintf(l('global.view_x_more'), nr($data->total_rows - count($data->rows))) ?></a>
        <?php endif ?>

    <?php endif ?>


    <?php if(is_logged_in() && !$this->team): ?>
    <div class="mt-4">
        <?php if($this->user->plan_settings->websites_goals_limit != -1 && $data->total_rows >= $this->user->plan_settings->websites_goals_limit): ?>
            <button type="button" class="btn btn-sm btn-block btn-primary disabled" data-toggle="tooltip" title="<?= l('global.info_message.plan_feature_limit') ?>">
                <?= l('global.create') ?>
            </button>
        <?php else: ?>
            <a href="#" class="btn btn-sm btn-block btn-primary" data-toggle="modal" data-target="#goal_create_modal" data-tooltip data-html="true" title="<?= get_plan_feature_limit_info($data->total_rows, $this->user->plan_settings->websites_goals_limit) ?>">
                <?= l('global.create') ?>
            </a>
        <?php endif ?>
    </div>
    <?php endif ?>
</div>
