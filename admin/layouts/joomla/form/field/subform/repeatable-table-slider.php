<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Layout
 *
 * @copyright   Copyright (C) 2005 - 2023 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

extract($displayData);

/**
 * Layout variables
 * -----------------
 * @var   string  $name        The field name
 * @var   string  $group       The field group
 * @var   string  $label       The field label
 * @var   string  $description The field description
 * @var   array   $options     The field options
 * @var   string  $value       The field value
 * @var   string  $class       The field class
 * @var   string  $id          The field id
 * @var   object  $field       The field object
 */

// Add CSS for better slider preview
$wa = Factory::getDocument()->getWebAssetManager();
$wa->addInlineStyle('
    .slider-preview-thumb {
        max-width: 80px;
        max-height: 60px;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
    .slider-field-table td {
        vertical-align: middle !important;
    }
    .slider-field-table .subform-repeatable-group {
        background: #f8f9fa;
        border-radius: 4px;
        margin-bottom: 10px;
        padding: 10px;
    }
    .slider-field-table .subform-repeatable-group .row {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    .slider-field-table .subform-repeatable-group .form-group {
        margin-bottom: 0;
    }
    .slider-preview-placeholder {
        width: 80px;
        height: 60px;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        color: #6c757d;
        font-size: 12px;
    }
');

// Get the current value as array
$valueArr = is_array($value) ? $value : json_decode($value, true);
if (!is_array($valueArr)) {
    $valueArr = [];
}

// Add row indexes
foreach ($valueArr as $index => &$row) {
    $row['_index'] = $index;
}
?>

<div class="subform-repeatable-wrapper">
    <table class="table table-striped table-hover slider-field-table" id="<?php echo $id; ?>">
        <thead>
            <tr>
                <th width="1%"><?php echo Text::_('JGRID_HEADING_ORDER'); ?></th>
                <th width="10%"><?php echo Text::_('COM_YOURCOMPONENT_FIELD_SLIDER_IMAGE_LABEL'); ?></th>
                <th><?php echo Text::_('COM_YOURCOMPONENT_FIELD_SLIDER_TITLE_LABEL'); ?></th>
                <th><?php echo Text::_('COM_YOURCOMPONENT_FIELD_SLIDER_DESCRIPTION_LABEL'); ?></th>
                <th><?php echo Text::_('COM_YOURCOMPONENT_FIELD_SLIDER_LINK_LABEL'); ?></th>
                <th width="10%"><?php echo Text::_('JACTION_DELETE'); ?></th>
            </tr>
        </thead>
        <tbody class="subform-repeatable-container" data-name="<?php echo $name; ?>" data-group="<?php echo $group; ?>">
            <?php if (empty($valueArr)) : ?>
                <tr class="no-items">
                    <td colspan="6" class="text-center text-muted">
                        <?php echo Text::_('COM_YOURCOMPONENT_NO_SLIDES'); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($valueArr as $i => $slide) : ?>
                    <tr class="subform-repeatable-group" data-group-index="<?php echo $i; ?>">
                        <td class="order-controls">
                            <span class="sortable-handler">
                                <span class="icon-ellipsis-v" aria-hidden="true"></span>
                            </span>
                            <input type="hidden" name="<?php echo $name; ?>[<?php echo $i; ?>][_index]" value="<?php echo $i; ?>">
                        </td>
                        <td>
                            <?php if (!empty($slide['image'])) : ?>
                                <img src="<?php echo htmlspecialchars($slide['image']); ?>" class="slider-preview-thumb" alt="<?php echo htmlspecialchars($slide['title'] ?? ''); ?>">
                            <?php else : ?>
                                <div class="slider-preview-placeholder">
                                    <span class="icon-image" aria-hidden="true"></span>
                                </div>
                            <?php endif; ?>
                            <br>
                            <input type="hidden" name="<?php echo $name; ?>[<?php echo $i; ?>][image]" value="<?php echo htmlspecialchars($slide['image'] ?? ''); ?>">
                            <button type="button" class="btn btn-secondary btn-sm select-image" data-target="<?php echo $name; ?>[<?php echo $i; ?>][image]">
                                <?php echo Text::_('COM_YOURCOMPONENT_SELECT_IMAGE'); ?>
                            </button>
                        </td>
                        <td>
                            <input type="text" name="<?php echo $name; ?>[<?php echo $i; ?>][title]" value="<?php echo htmlspecialchars($slide['title'] ?? ''); ?>" class="form-control" placeholder="<?php echo Text::_('COM_YOURCOMPONENT_SLIDE_TITLE_PLACEHOLDER'); ?>">
                        </td>
                        <td>
                            <textarea name="<?php echo $name; ?>[<?php echo $i; ?>][description]" class="form-control" rows="2" placeholder="<?php echo Text::_('COM_YOURCOMPONENT_SLIDE_DESCRIPTION_PLACEHOLDER'); ?>"><?php echo htmlspecialchars($slide['description'] ?? ''); ?></textarea>
                        </td>
                        <td>
                            <input type="text" name="<?php echo $name; ?>[<?php echo $i; ?>][link]" value="<?php echo htmlspecialchars($slide['link'] ?? ''); ?>" class="form-control" placeholder="https://example.com">
                            <select name="<?php echo $name; ?>[<?php echo $i; ?>][target]" class="form-control form-control-sm">
                                <option value="_self" <?php echo (($slide['target'] ?? '_self') === '_self') ? 'selected' : ''; ?>><?php echo Text::_('COM_YOURCOMPONENT_FIELD_SLIDER_TARGET_SELF'); ?></option>
                                <option value="_blank" <?php echo (($slide['target'] ?? '_self') === '_blank') ? 'selected' : ''; ?>><?php echo Text::_('COM_YOURCOMPONENT_FIELD_SLIDER_TARGET_BLANK'); ?></option>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm remove-item">
                                <span class="icon-remove" aria-hidden="true"></span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">
                    <button type="button" class="btn btn-success btn-sm add-item">
                        <span class="icon-plus" aria-hidden="true"></span>
                        <?php echo Text::_('COM_YOURCOMPONENT_ADD_SLIDE'); ?>
                    </button>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Obsługa dodawania nowych slajdów
        const addButtons = document.querySelectorAll('.add-item');
        addButtons.forEach(button => {
            button.addEventListener('click', function() {
                const table = this.closest('table');
                const tbody = table.querySelector('tbody');
                const container = tbody;
                const name = container.dataset.name;
                
                // Znajdź aktualną liczbę wierszy
                const rows = container.querySelectorAll('tr.subform-repeatable-group');
                const newIndex = rows.length;
                
                // Stwórz nowy wiersz
                const tr = document.createElement('tr');
                tr.className = 'subform-repeatable-group';
                tr.dataset.groupIndex = newIndex;
                
                tr.innerHTML = `
                    <td class="order-controls">
                        <span class="sortable-handler">
                            <span class="icon-ellipsis-v" aria-hidden="true"></span>
                        </span>
                        <input type="hidden" name="${name}[${newIndex}][_index]" value="${newIndex}">
                    </td>
                    <td>
                        <div class="slider-preview-placeholder">
                            <span class="icon-image" aria-hidden="true"></span>
                        </div>
                        <br>
                        <input type="hidden" name="${name}[${newIndex}][image]" value="">
                        <button type="button" class="btn btn-secondary btn-sm select-image" data-target="${name}[${newIndex}][image]">
                            <?php echo Text::_('COM_YOURCOMPONENT_SELECT_IMAGE'); ?>
                        </button>
                    </td>
                    <td>
                        <input type="text" name="${name}[${newIndex}][title]" class="form-control" placeholder="<?php echo Text::_('COM_YOURCOMPONENT_SLIDE_TITLE_PLACEHOLDER'); ?>">
                    </td>
                    <td>
                        <textarea name="${name}[${newIndex}][description]" class="form-control" rows="2" placeholder="<?php echo Text::_('COM_YOURCOMPONENT_SLIDE_DESCRIPTION_PLACEHOLDER'); ?>"></textarea>
                    </td>
                    <td>
                        <input type="text" name="${name}[${newIndex}][link]" class="form-control" placeholder="https://example.com">
                        <select name="${name}[${newIndex}][target]" class="form-control form-control-sm">
                            <option value="_self"><?php echo Text::_('COM_YOURCOMPONENT_FIELD_SLIDER_TARGET_SELF'); ?></option>
                            <option value="_blank"><?php echo Text::_('COM_YOURCOMPONENT_FIELD_SLIDER_TARGET_BLANK'); ?></option>
                        </select>
                    </td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-item">
                            <span class="icon-remove" aria-hidden="true"></span>
                        </button>
                    </td>
                `;
                
                // Usuń komunikat "no items"
                const noItems = container.querySelector('.no-items');
                if (noItems) {
                    noItems.remove();
                }
                
                container.appendChild(tr);
                
                // Obsługa przycisku usuwania dla nowego wiersza
                tr.querySelector('.remove-item').addEventListener('click', function() {
                    const row = this.closest('tr');
                    const container = row.closest('tbody');
                    row.remove();
                    
                    // Jeśli nie ma wierszy, pokaż komunikat "no items"
                    if (container.querySelectorAll('tr.subform-repeatable-group').length === 0) {
                        const trNoItems = document.createElement('tr');
                        trNoItems.className = 'no-items';
                        trNoItems.innerHTML = `<td colspan="6" class="text-center text-muted"><?php echo Text::_('COM_YOURCOMPONENT_NO_SLIDES'); ?></td>`;
                        container.appendChild(trNoItems);
                    }
                });
            });
        });

        // Obsługa usuwania istniejących slajdów
        document.querySelectorAll('.remove-item').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                const container = row.closest('tbody');
                row.remove();
                
                if (container.querySelectorAll('tr.subform-repeatable-group').length === 0) {
                    const trNoItems = document.createElement('tr');
                    trNoItems.className = 'no-items';
                    trNoItems.innerHTML = `<td colspan="6" class="text-center text-muted"><?php echo Text::_('COM_YOURCOMPONENT_NO_SLIDES'); ?></td>`;
                    container.appendChild(trNoItems);
                }
            });
        });

        // Obsługa wyboru obrazka (używając Joomla Media Manager)
        document.querySelectorAll('.select-image').forEach(button => {
            button.addEventListener('click', function() {
                const targetInput = document.getElementById(this.dataset.target) || document.querySelector(`input[name="${this.dataset.target}"]`);
                
                if (targetInput) {
                    // Otwórz Joomla Media Manager
                    const url = 'index.php?option=com_media&view=images&tmpl=component&asset=com_thapi&author=&fieldid=' + encodeURIComponent(targetInput.id);
                    
                    const modal = new Joomla.Modal({
                        url: url,
                        height: '400px',
                        width: '800px',
                        backdrop: true,
                        keyboard: true,
                        closeButton: true,
                        onClose: function() {
                            // Po zamknięciu - obrazek zostanie wstawiony przez Joomla
                        }
                    });
                    
                    modal.open();
                }
            });
        });

        // Sortowanie - podstawowa obsługa przeciągania
        const tables = document.querySelectorAll('.slider-field-table tbody');
        tables.forEach(table => {
            let dragSrcIndex = null;
            
            table.addEventListener('dragstart', function(e) {
                if (e.target.closest('tr.subform-repeatable-group')) {
                    const row = e.target.closest('tr');
                    dragSrcIndex = row.dataset.groupIndex;
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', dragSrcIndex);
                }
            });

            table.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            table.addEventListener('drop', function(e) {
                e.preventDefault();
                const targetRow = e.target.closest('tr.subform-repeatable-group');
                if (!targetRow || dragSrcIndex === null) return;
                
                const srcRow = table.querySelector(`[data-group-index="${dragSrcIndex}"]`);
                if (!srcRow || srcRow === targetRow) return;
                
                // Przenieś wiersz
                if (srcRow.compareDocumentPosition(targetRow) & Node.DOCUMENT_POSITION_FOLLOWING) {
                    table.insertBefore(srcRow, targetRow.nextSibling);
                } else {
                    table.insertBefore(srcRow, targetRow);
                }
                
                // Aktualizuj indeksy
                table.querySelectorAll('tr.subform-repeatable-group').forEach((row, index) => {
                    row.dataset.groupIndex = index;
                    const hiddenInput = row.querySelector('input[type="hidden"][name*="[_index]"]');
                    if (hiddenInput) {
                        hiddenInput.value = index;
                        hiddenInput.name = hiddenInput.name.replace(/\[\d+\]/, `[${index}]`);
                    }
                });
                
                dragSrcIndex = null;
            });
        });
    });
</script>
