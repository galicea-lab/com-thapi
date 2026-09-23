<?php
// admin/src/Field/SliderField.php

namespace ThApi\Component\ThApi\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

class SliderField extends ListField
{
    public $type = 'Slider';

    protected function getInput()
    {
        $doc = Factory::getDocument();
        
        // Pobierz obecną wartość
        $value = $this->value;
        $slides = is_array($value) ? $value : json_decode($value, true);
        if (!is_array($slides)) {
            $slides = [];
        }

        // Unikalne ID dla tego pola
        $fieldId = $this->id;
        
        // Generuj HTML
        $html = [];
        $html[] = '<div id="' . $fieldId . '_wrapper" class="slider-field-wrapper">';
        
        // Przycisk dodawania
        $html[] = '<button type="button" class="btn btn-success btn-sm mb-3" onclick="addSlide_' . $fieldId . '()">';
        $html[] = '<span class="icon-plus"></span> ' . Text::_('COM_THAPI_ADD_SLIDE');
        $html[] = '</button>';
        
        // Lista slajdów
        $html[] = '<div id="' . $fieldId . '_slides" class="slider-slides-container">';
        
        if (empty($slides)) {
            $html[] = '<div class="alert alert-info">' . Text::_('COM_THAPI_NO_SLIDES') . '</div>';
        } else {
            foreach ($slides as $index => $slide) {
                $html[] = $this->getSlideHtml($fieldId, $index, $slide);
            }
        }
        
        $html[] = '</div>'; // koniec slides-container
        
        // Ukryte pole do przechowywania danych JSON
        $html[] = '<input type="hidden" name="' . $this->name . '" id="' . $fieldId . '" value="' . htmlspecialchars(json_encode($slides)) . '" />';
        
        $html[] = '</div>'; // koniec wrapper
        
        // Dodaj JavaScript
        $js = $this->getJavaScript($fieldId);
        $doc->addScriptDeclaration($js);
        
        // Dodaj CSS
        $css = $this->getCss();
        $doc->addStyleDeclaration($css);
        
        return implode("\n", $html);
    }

    private function getSlideHtml($fieldId, $index, $slide)
    {
        $image = $slide['image'] ?? '';
        $title = $slide['title'] ?? '';
        $description = $slide['description'] ?? '';
        $link = $slide['link'] ?? '';
        $target = $slide['target'] ?? '_self';
        $root = Uri::root();

        $html = [];
        $html[] = '<div class="slide-item card mb-3" data-index="' . $index . '" id="' . $fieldId . '_slide_' . $index . '">';
        $html[] = '  <div class="card-body">';
        $html[] = '    <div class="row">';
        $html[] = '      <div class="col-md-3">';
        $html[] = '        <div class="slide-image-preview">';
        
        if ($image) {
            $fullImage = (strpos($image, 'http') === 0) ? $image : $root . ltrim($image, '/');
            $html[] = '  <img src="' . htmlspecialchars($fullImage) . '" class="img-fluid" style="max-height:150px;max-width:100%;" />';
        } else {
            $html[] = '  <div class="empty-image-placeholder" style="height:150px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;border-radius:4px;">';
            $html[] = '    <span class="text-muted">' . Text::_('COM_THAPI_NO_IMAGE') . '</span>';
            $html[] = '  </div>';
        }
        
        $html[] = '        </div>';
        $html[] = '        <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="selectImage_' . $fieldId . '(' . $index . ')">';
        $html[] = '          <span class="icon-folder"></span> ' . Text::_('COM_THAPI_SELECT_IMAGE');
        $html[] = '        </button>';
        $html[] = '        <button type="button" class="btn btn-danger btn-sm mt-2" onclick="removeSlide_' . $fieldId . '(' . $index . ')">';
        $html[] = '          <span class="icon-remove"></span> ' . Text::_('JACTION_DELETE');
        $html[] = '        </button>';
        $html[] = '      </div>';
        $html[] = '      <div class="col-md-9">';
        $html[] = '        <div class="form-group">';
        $html[] = '          <label>' . Text::_('COM_THAPI_SLIDE_TITLE') . '</label>';
        $html[] = '          <input type="text" class="form-control" value="' . htmlspecialchars($title) . '" ';
        $html[] = '            onchange="updateSlide_' . $fieldId . '(' . $index . ', \'title\', this.value)" />';
        $html[] = '        </div>';
        $html[] = '        <div class="form-group">';
        $html[] = '          <label>' . Text::_('COM_THAPI_SLIDE_DESCRIPTION') . '</label>';
        $html[] = '          <textarea class="form-control" rows="2" ';
        $html[] = '            onchange="updateSlide_' . $fieldId . '(' . $index . ', \'description\', this.value)">' . htmlspecialchars($description) . '</textarea>';
        $html[] = '        </div>';
        $html[] = '        <div class="row">';
        $html[] = '          <div class="col-md-8">';
        $html[] = '            <div class="form-group">';
        $html[] = '              <label>' . Text::_('COM_THAPI_SLIDE_LINK') . '</label>';
        $html[] = '              <input type="text" class="form-control" value="' . htmlspecialchars($link) . '" ';
        $html[] = '                onchange="updateSlide_' . $fieldId . '(' . $index . ', \'link\', this.value)" ';
        $html[] = '                placeholder="https://example.com" />';
        $html[] = '            </div>';
        $html[] = '          </div>';
        $html[] = '          <div class="col-md-4">';
        $html[] = '            <div class="form-group">';
        $html[] = '              <label>' . Text::_('COM_THAPI_SLIDE_TARGET') . '</label>';
        $html[] = '              <select class="form-control" onchange="updateSlide_' . $fieldId . '(' . $index . ', \'target\', this.value)">';
        $html[] = '                <option value="_self"' . ($target == '_self' ? ' selected' : '') . '>' . Text::_('COM_THAPI_TARGET_SELF') . '</option>';
        $html[] = '                <option value="_blank"' . ($target == '_blank' ? ' selected' : '') . '>' . Text::_('COM_THAPI_TARGET_BLANK') . '</option>';
        $html[] = '              </select>';
        $html[] = '            </div>';
        $html[] = '          </div>';
        $html[] = '        </div>';
        $html[] = '      </div>';
        $html[] = '    </div>';
        $html[] = '  </div>';
        $html[] = '</div>';
        
        return implode("\n", $html);
    }

    private function getJavaScript($fieldId)
    {
        return <<<JS
        // Funkcje dla slidera {$fieldId}
        var slides_{$fieldId} = [];
        
        function initSlides_{$fieldId}() {
            const hiddenField = document.getElementById('{$fieldId}');
            if (hiddenField && hiddenField.value) {
                try {
                    slides_{$fieldId} = JSON.parse(hiddenField.value);
                } catch(e) {
                    slides_{$fieldId} = [];
                }
            } else {
                slides_{$fieldId} = [];
            }
        }
        
        function saveSlides_{$fieldId}() {
            const hiddenField = document.getElementById('{$fieldId}');
            if (hiddenField) {
                hiddenField.value = JSON.stringify(slides_{$fieldId});
                // Wywołaj zdarzenie change dla walidacji
                hiddenField.dispatchEvent(new Event('change'));
            }
        }
        
        function renderSlides_{$fieldId}() {
            const container = document.getElementById('{$fieldId}_slides');
            if (!container) return;
            
            if (slides_{$fieldId}.length === 0) {
                container.innerHTML = '<div class="alert alert-info">' + Joomla.JText._('COM_THAPI_NO_SLIDES') + '</div>';
                saveSlides_{$fieldId}();
                return;
            }
            
            // Tutaj powinno być odświeżenie listy slajdów
            // W uproszczeniu - odświeżamy całą stronę lub używamy bardziej zaawansowanego renderowania
            location.reload();
        }
        
        function addSlide_{$fieldId}() {
            const newSlide = {
                image: '',
                title: '',
                description: '',
                link: '',
                target: '_self'
            };
            slides_{$fieldId}.push(newSlide);
            saveSlides_{$fieldId}();
            renderSlides_{$fieldId}();
        }
        
        function removeSlide_{$fieldId}(index) {
            if (confirm(Joomla.JText._('COM_THAPI_CONFIRM_DELETE_SLIDE'))) {
                slides_{$fieldId}.splice(index, 1);
                saveSlides_{$fieldId}();
                renderSlides_{$fieldId}();
            }
        }
        
        function updateSlide_{$fieldId}(index, field, value) {
            if (slides_{$fieldId}[index]) {
                slides_{$fieldId}[index][field] = value;
                saveSlides_{$fieldId}();
            }
        }
        
        function selectImage_{$fieldId}(index) {
            // Generuj unikalne ID dla pola obrazka
            const fieldName = '{$fieldId}_image_' + index;
            
            // Otwórz Media Manager
            const url = 'index.php?option=com_media&view=images&tmpl=component&asset=com_thapi&author=&fieldid=' + fieldName;
            
            const modal = new Joomla.Modal({
                url: url,
                height: '400px',
                width: '800px',
                backdrop: true,
                keyboard: true,
                closeButton: true,
                onClose: function() {
                    // Sprawdź czy wybrano obrazek
                    const imageInput = document.getElementById(fieldName);
                    if (imageInput && imageInput.value) {
                        updateSlide_{$fieldId}(index, 'image', imageInput.value);
                        renderSlides_{$fieldId}();
                    }
                }
            });
            
            modal.open();
        }
        
        // Nasłuchuj na wybór obrazka z Media Managera
        document.addEventListener('DOMContentLoaded', function() {
            // Obsługa wyboru obrazka z Media Managera
            window.addEventListener('message', function(event) {
                if (event.data && event.data.type === 'media-selected') {
                    const fieldName = event.data.fieldId;
                    if (fieldName && fieldName.indexOf('{$fieldId}_image_') === 0) {
                        const index = parseInt(fieldName.replace('{$fieldId}_image_', ''));
                        const imageInput = document.getElementById(fieldName);
                        if (imageInput) {
                            imageInput.value = event.data.url;
                            updateSlide_{$fieldId}(index, 'image', event.data.url);
                            renderSlides_{$fieldId}();
                        }
                    }
                }
            });
        });
        
        // Inicjalizacja
        initSlides_{$fieldId}();
JS;
    }

    private function getCss()
    {
        return <<<CSS
        .slider-field-wrapper .slide-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .slider-field-wrapper .slide-item .card-body {
            padding: 15px;
        }
        .slider-field-wrapper .empty-image-placeholder {
            background: #e9ecef;
            border-radius: 4px;
        }
        .slider-field-wrapper .slide-image-preview {
            text-align: center;
        }
        .slider-field-wrapper .slide-image-preview img {
            border-radius: 4px;
            max-height: 150px;
        }
CSS;
    }
}