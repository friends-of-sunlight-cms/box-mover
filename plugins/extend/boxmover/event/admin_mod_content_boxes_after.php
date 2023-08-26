<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Plugin\TemplatePlugin;
use Sunlight\Template;
use Sunlight\Util\Response;
use Sunlight\Xsrf;

return function (array $args) {
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
    if (isset($_POST['move_boxes_submit'], $_POST['move']) && count($_POST['move']) > 0) {
        moveSelectedBoxes($activeTemplate, $boxes);
    }

    $tableHead = $hasMovableBoxes ? '<thead>
                    <tr>
                        <th><input type="checkbox" class="selectall" onchange="var that=this;$(\'table.boxmover-list input[type=checkbox][name^=move]\').each(function() {this.checked=that.checked;});" checked></th>
                        <th>' . _lang('boxmover.row.title') . '</th>
                        <th>' . _lang('boxmover.current.location') . '</th>
                    </tr>
                </thead>' : '';

    // render table
    $output = "<form id='boxmover_form' name='boxmover_form' action='' method='post'>
                    <table class='boxmover-list box-list list list-hover list-max'>
                        <caption><h2>" . _lang('boxmover.caption') . "</h2></caption>
                        " . $tableHead . "
                        <tbody>";

    if ($hasMovableBoxes) {

        // render boxes
        foreach ($boxes as $box) {
            $boxParent = Core::$pluginManager->getPlugins()->getTemplate($box['template']);

            $output .= '<tr>
                                <td><input id="move_' . $box['id'] . '" type="checkbox" name="move[' . $box['id'] . ']" value="1" checked></td>
                                <td><label for="move_' . $box['id'] . '">' . $box['title'] . '</label></td>
                                <td><label for="move_' . $box['id'] . '">'
                . $boxParent->getCamelCasedName()
                . _e(sprintf(' (%s - %s)', $boxParent->getLayoutLabel($box['layout']), $boxParent->getSlotLabel($box['layout'], $box['slot'])))
                . '</label></td>
                        </tr>';
        }

        $output .= '</tbody>';
        $output .= '<tfoot> 
                <tr>
                    <td colspan="3">
                        <div class="right">
                            <div class="left">
                                <input type="text" value="' . $activeTemplate->getName() . '" disabled> ' . createLayoutSelect($activeTemplate) . '<br>
                                <input id="convert_slots" type="checkbox" name="convert_slots" value="1" checked>
                                <label for="convert_slots">' . _lang('boxmover.convert.slots') . '</label>
                            </div>
                            <button type="submit" name="move_boxes_submit" onclick="return Sunlight.confirm();">
                                <img src="./public/images/icons/action.png" alt="move" class="icon"> '
            . _lang('boxmover.submit')
            . '</button><br>
                        </div>
                    </td>
                </tr>
            </tfoot>';
    } else {
        $output .= '<tr><td colspan="3">' . Message::warning(_lang('boxmover.no.boxes')) . '</td></tr>';
    }

    $output .= '</table>';
    $output .= Xsrf::getInput();
    $output .= '</form>';

    $args['output'] .= $output;
};

function createLayoutSelect(TemplatePlugin $activeTemplate): string
{
    $layouts = $activeTemplate->getLayouts();

    $output = '<select name="layout">';
    foreach ($layouts as $layout) {
        $output .= '<option value="' . $layout . '">'
            . _lang('admin.content.layout') . ": " . $activeTemplate->getLayoutLabel($layout)
            . '</option>';
    }
    $output .= '</select>';

    return $output;
}

/**
 * Moving boxes to active template
 */
function moveSelectedBoxes(TemplatePlugin $activeTemplate, array $boxes): void
{
    // get layout and slots
    $active_template_id = $activeTemplate->getName();
    $selected_layout = DB::esc($_POST['layout']);
    $slots = $activeTemplate->getSlots($selected_layout);

    if (count($slots) > 0) {
        $flipped_slots = array_flip($slots);

        $prepare = [];
        $ids = array_keys($_POST['move']);
        foreach ($ids as $id) {
            if (isset($boxes[$id])) {
                // copy data
                $prepare[$id] = $boxes[$id];

                // remove unused
                unset($prepare[$id]['id'], $prepare[$id]['title']);

                // set new values
                $prepare[$id]['template'] = $active_template_id;
                $prepare[$id]['layout'] = $selected_layout;

                // convert slot if required
                if (isset($_POST['convert_slots'])) {
                    if (!isset($flipped_slots[$prepare[$id]['slot']])) {
                        $prepare[$id]['slot'] = $slots[0];
                    }
                }
            }
        }

        // save
        if (count($prepare) > 0) {
            foreach ($prepare as $k => $v) {
                DB::update('box', 'id=' . $k, $v);
            }
            // redirect
            Response::redirect('index.php?p=content-boxes&moved');
        }
    }
}
