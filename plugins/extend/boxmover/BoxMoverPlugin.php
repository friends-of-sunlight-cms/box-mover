<?php

namespace SunlightExtend\Boxmover;

use Sunlight\Database\Database as DB;
use Sunlight\Plugin\ExtendPlugin;
use Sunlight\Plugin\TemplatePlugin;
use Sunlight\Template;
use Sunlight\Util\Response;
use Sunlight\Xsrf;

/**
 * Class BoxMoverPlugin
 *
 * @author Jirka Daněk <jdanek.eu>
 */
class BoxMoverPlugin extends ExtendPlugin
{
    /** @var TemplatePlugin */
    protected $activeTemplate;

    /** @var array */
    protected $boxes = [];

    /**
     * @param $args
     */
    public function afterBoxList($args): void
    {
        // get active template
        $this->activeTemplate = Template::getCurrent();
        // load all boxes
        $this->boxes = DB::queryRows("SELECT id, title, template, layout, slot FROM " . DB::table('box'), 'id');

        // process POST
        if (isset($_POST['move_boxes_submit'], $_POST['move']) && count($_POST['move']) > 0) {
            $this->moveSelectedBoxes();
        }

        // render table
        $output = "<form id='boxmover_form' name='boxmover_form' action='' method='post'><table class='box-list list list-hover list-max'>
            <caption><h2>" . _lang('boxmover.caption') . "</h2></caption>
                <thead>
                    <tr>
                        <th><input type='checkbox' class='selectall'></th>
                        <th>" . _lang('boxmover.row.title') . "</th>
                    </tr>
                </thead>
            <tbody>";

        foreach ($this->boxes as $box) {
            $output .= "<tr>
                            <td><input id='move_" . $box['id'] . "' type='checkbox' name='move[" . $box['id'] . "]' value='1'></td>
                            <td><label for='move_" . $box['id'] . "'>" . $box['title'] . "</label></td>
                        </tr>";
        }

        $output .= "</tbody>
            <tfoot> 
                <tr>
                    <td></td>
                    <td>
                        <div class='right'>"

            . "<div class='left'>"
            . "<input type='text' value='" . $this->activeTemplate->getCamelId() . "' disabled> "
            . $this->createLayoutSelect() . "<br>"
            . "<input id='convert_slots' type='checkbox' name='convert_slots' value='1' checked><label for='convert_slots'>" . _lang('boxmover.convert.slots') . "</label>"
            . "</div>"
            . "<button type='submit' name='move_boxes_submit' onclick='return Sunlight.confirm();'><img src='./images/icons/action.png' alt='move' class='icon'> " . _lang('boxmover.submit') . "</button><br>
                        </div>
                    </td>
                </tr>
            </tfoot>
        
            </table>";
        $output .= Xsrf::getInput();
        $output .= "</form>";

        // select all
        $output .= "<script>$('.selectall').click(function() { $('input[type=checkbox][name^=move]').each(function () { this.checked = !this.checked; }); });</script>";

        $args['output'] .= $output;
    }

    /**
     * @return string
     */
    public function createLayoutSelect(): string
    {
        $layouts = $this->activeTemplate->getLayouts();

        $output = "<select name='layout'>";
        foreach ($layouts as $layout) {
            $output .= "<option value='" . $layout . "'>" . _lang('admin.content.layout') . ": " . $this->activeTemplate->getLayoutLabel($layout) . "</option>";
        }
        $output .= "</select>";

        return $output;
    }

    /**
     * Moving boxes to active template
     */
    public function moveSelectedBoxes(): void
    {
        // get layout and slots
        $active_template_id = $this->activeTemplate->getId();
        $selected_layout = DB::esc($_POST['layout']);
        $slots = $this->activeTemplate->getSlots($selected_layout);

        if (count($slots) > 0) {
            $flipped_slots = array_flip($slots);

            $prepare = [];
            $ids = array_keys($_POST['move']);
            foreach ($ids as $id) {
                if (isset($this->boxes[$id])) {
                    // copy data
                    $prepare[$id] = $this->boxes[$id];
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
}