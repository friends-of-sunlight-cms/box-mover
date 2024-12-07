<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Plugin\TemplateService;
use Sunlight\Router;
use Sunlight\Template;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\Response;
use Sunlight\Xsrf;

return new class {

    public function __invoke(array $args)
    {
        // get active template
        $activeTemplate = Template::getCurrent();
        // load all boxes
        $boxes = DB::queryRows(
            'SELECT id, title, template, layout, slot FROM ' . DB::table('box')
            . ' WHERE template!=' . DB::val($activeTemplate->getName()),
            'id'
        );
        $hasMovableBoxes = !empty($boxes);

        // process POST
        if (isset($_POST['move_boxes_submit'], $_POST['slot_uid'], $_POST['move']) && count($_POST['move']) > 0) {
            $this->moveSelectedBoxes(Request::post('slot_uid'), $boxes);
        }

        // render table
        $tableHead = !$hasMovableBoxes ? '' : _buffer(function () { ?>
            <thead>
            <tr>
                <th>
                    <?= Form::input('checkbox', null, null, [
                        'onchange' => 'var that=this;$(\'table.boxmover-list input[type=checkbox][name^=move]\').each(function() {this.checked=that.checked;});',
                        'checked' => true,
                        'class' => 'selectall',
                    ]) ?>
                </th>
                <th><?= _lang('boxmover.row.title') ?></th>
                <th><?= _lang('boxmover.current.location') ?></th>
            </tr>
            </thead>
            <?php
        });

        $output = _buffer(function () use ($tableHead, $activeTemplate, $hasMovableBoxes, $boxes) { ?>
            <form id='boxmover_form' name='boxmover_form' action='' method='post'>
                <table class='boxmover-list box-list list list-hover list-max'>
                    <caption><h2><?= _lang('boxmover.caption') ?></h2></caption>
                    <?= $tableHead ?>
                    <tbody>

                    <?php
                    if ($hasMovableBoxes): ?>

                    <?php
                    foreach ($boxes as $box): ?>
                        <?php
                        $boxParent = Core::$pluginManager->getPlugins()->getTemplate($box['template']); ?>
                        <tr>
                            <td>
                                <?= Form::input('checkbox', 'move[' . $box['id'] . ']', '1', ['id' => 'move_' . $box['id'], 'checked' => true]) ?>
                            </td>
                            <td><label for="move_<?= $box['id'] ?>"><?= $box['title'] ?></label></td>
                            <td>
                                <label for="move_<?= $box['id'] ?>">
                                    <?= $boxParent->getCamelCasedName() ?>
                                    <?= _e(sprintf(' (%s - %s)', $boxParent->getLayoutLabel($box['layout']), $boxParent->getSlotLabel($box['layout'], $box['slot']))) ?>
                                </label>
                            </td>
                        </tr>
                    <?php
                    endforeach; ?>

                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="3">
                            <div class="right">
                                <div class="left">
                                    <?= Admin::templateLayoutSlotSelect('slot_uid', null, null, '', [$activeTemplate]) ?>
                                </div>
                                <button type="submit" name="move_boxes_submit" onclick="return Sunlight.confirm();">
                                    <img src="<?= Router::path('admin/public/images/icons/action.png') ?>" alt="move" class="icon">
                                    <?= _lang('boxmover.submit') ?>
                                </button>
                                <br>
                            </div>
                        </td>
                    </tr>
                    </tfoot>
                <?php
                else: ?>
                    <tr>
                        <td colspan="3"><?= Message::warning(_lang('boxmover.no.boxes')) ?></td>
                    </tr>
                <?php
                endif; ?>
                </table>
                <?= Xsrf::getInput() ?>
            </form>
            <?php
        });

        $args['output'] .= $output;
    }

    /**
     * Moving boxes to active template
     */
    public function moveSelectedBoxes(string $slotUid, array $boxes): void
    {
        [$template, $layout, $slot] = explode(':', $slotUid);

        if (
            TemplateService::templateExists($template)
            && TemplateService::getTemplate($template)->hasSlot($layout, $slot)
        ) {
            $ids = array_keys($_POST['move']);

            $counter = 0;
            foreach ($ids as $id) {
                if (isset($boxes[$id])) {
                    DB::update('box', 'id=' . DB::val($id), [
                        'template' => $template,
                        'layout' => $layout,
                        'slot' => $slot,
                    ]);
                    ++$counter;
                }
            }
            if ($counter > 0) {
                Response::redirect('index.php?p=content-boxes&moved');
            }
        }
    }
};
