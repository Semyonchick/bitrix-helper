<?php

/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 01.03.2016
 * Time: 14:53
 */

global $APPLICATION;
Helpers::$app = $APPLICATION;

class Helpers
{
    /** @var CMain */
    static $app;
    static $svgSprite;

    static function svgSprite($name, $options){
        $tags = '';
        foreach ($options as $key => $value) $tags .= " {$key}=\"{$value}\"";
        return '<svg '.$tags.'><use xlink:href="'.self::$svgSprite.'#'.$name.'"></use></svg>';
    }

    static function renderData($name)
    {
        ob_start();
        self::renderIncludeFile($name);
        $out = ob_get_contents();
        ob_end_clean();
        return $out;
    }

    static function isDirOf($dir)
    {
        return preg_match('#^' . addslashes($dir) . '(index\.php)?(\?.+)?$#', self::$app->GetCurPage(true));
    }

    static function renderIncludeFile($name, $data = array())
    {
        self::$app->IncludeComponent(
            "bitrix:main.include",
            "",
            Array(
                "AREA_FILE_SHOW" => "file",
                "AREA_FILE_SUFFIX" => "inc",
                "EDIT_TEMPLATE" => "",
                "DATA" => $data,
                "PATH" => SITE_DIR . "/local/include/{$name}.php"
            )
        );
    }

    /**
     * @param $template CBitrixComponentTemplate
     * @param $item array
     */
    static function editButtons($template, $item)
    {
        $type = isset($item['DEPTH_LEVEL']) ? 'SECTION' : 'ELEMENT';
        if (isset($item['EDIT_LINK'])) $template->AddEditAction($item['ID'], $item['EDIT_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "{$type}_EDIT"));
        if (isset($item['DELETE_LINK'])) $template->AddDeleteAction($item['ID'], $item['DELETE_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "{$type}_DELETE"), array("CONFIRM" => GetMessage('CT_BCSL_ELEMENT_DELETE_CONFIRM')));
        return $template->GetEditAreaId($item['ID']);
    }

    static function img($arPhoto, $width, $height, $defaultOptions = array())
    {
        $options = array();
        if (is_array($arPhoto)) {
            foreach(['ALT', 'TITLE'] as $attr)
                if (!empty($arElement[$attr])) {
                    $options[strtolower($attr)] = $arElement[$attr];
                }
            $img = $arPhoto['ID'];
        } else {
            $img = $arPhoto;
        }

        if (empty($img)) {
            global $APPLICATION;
            return CFile::ShowImage($APPLICATION->GetTemplatePath('static/img/content/no-photo.png'), $width, $height, "border=0 class='image-center'", "", false);
        }

        $format = BX_RESIZE_IMAGE_PROPORTIONAL;
        if (!empty($defaultOptions['crop'])) {
            $format = BX_RESIZE_IMAGE_EXACT;
        } else if (!empty($defaultOptions['vertical'])) {
            $format = BX_RESIZE_IMAGE_PROPORTIONAL_ALT;
        }

        $image = self::convert($img, array($width, $height), $format);
        foreach($image as $key => $value) if(!$value) unset($image[$key]);
        if (!empty($defaultOptions['dataLazy']) || !empty($defaultOptions['lazy'])) {
            $image['data-lazy'] = $image['src'];
            unset($image['src']);
        }
        foreach (array('dataLazy', 'lazy', 'crop', 'vertical') as $name)
            if (isset($defaultOptions[$name])) unset($defaultOptions[$name]);

        if (isset($defaultOptions['lazy'])) unset($defaultOptions['lazy']);
        $options = array_merge($options, $image);
        $options = array_merge($options, $defaultOptions);
        if($options['size']) unset($options['size']);
        $tags = '';
        foreach ($options as $key => $value) $tags .= " {$key}=\"{$value}\"";
        return '<img' . $tags . '>';
    }

    static function convert($imageId, $sizes, $format = BX_RESIZE_IMAGE_PROPORTIONAL)
    {
        /*if (min($sizes) > 300) $arWaterMark = Array(
            array(
                "name" => "watermark",
                "position" => "bottomleft",
                "type" => "image",
                "size" => "resize",
                "file" => $_SERVER["DOCUMENT_ROOT"] . "/local/templates/dmt_rere/watermark.png",
//                "fill" => "repeat",
                "coefficient" => 0.24,
            )
        );*/
        return CFile::ResizeImageGet($imageId, array('width' => $sizes[0], 'height' => $sizes[1]), $format, true, isset($arWaterMark)?$arWaterMark:false);
    }


    static function fieldBlock($name, $value, $itemOptions, $options = array())
    {
        $options['class'] = isset($options['class']) ? $options['class'] : 'form-group field-' . $name;
        if (isset($options['required'])) {
            if ($options['required']) {
                $options['class'] .= ' required';
                $itemOptions['required'] = true;
            }
            unset($options['required']);
        }
        $error = '';
        if (isset($options['error'])) {
            if ($options['required']) {
                $options['class'] .= ' has-error';
                $error = $options['error'];
            }
            unset($options['error']);
        }
        $type = isset($itemOptions['type']) ? $itemOptions['type'] : 'text';
        $input = self::field($type, $name, $value, $itemOptions);

        if ($type == 'hidden') return $input;
        return '<div ' . self::options($options) . '> ' . $input . ' <div class="help-block">' . $error . '</div> </div>';
    }

    static function field($type, $name, $value, $options)
    {
        $options['name'] = $name;
        if (!in_array($type, array('textarea', 'dropdown'))) {
            $options['type'] = $type;
            $options['value'] = $value;
        }

        if ($options['placeholder'] == '') {
            $options['value'] = (strpos($_SERVER['REQUEST_URI'], '/forms/') === 0) &&
            strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) ?
                $_SERVER['HTTP_REFERER'] :
                'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }


        if ($type == 'textarea') {
            if (empty($options['rows'])) $options['rows'] = 5;
            return '<textarea ' . self::options($options) . '>' . $value . '</textarea>';
        } elseif ($type == 'dropdown') {
            $data = '';
            if ($options['placeholder'])
                $data .= "<option>{$options['placeholder']}</option>";
            foreach ($options['list']['reference'] as $key => $row)
                $data .= "<option value='{$options['list']['reference_id'][$key]}' " . ($value == $options['list']['reference_id'][$key] ? 'selected' : '') . ">{$row}</option>";
            return '<select ' . self::options($options) . '>' . $data . '</select>';
        }
        return '<input ' . self::options($options) . '>';
    }

    static function options($options)
    {
        $optionsFormat = array();
        foreach ($options as $key => $value) if ($value !== false)
            $optionsFormat[] = $value === true ? $key : "{$key}=\"{$value}\"";

        return implode(' ', $optionsFormat);
    }

    /**
     * @param $number
     * Количество для скланения
     * @param $titles
     * массив в виде <code>['одна', 'две', 'пять']</code>
     * для подстановки цифры используется символ <i>%</i>
     * @return string
     */
    public static function numeric($number, $titles)
    {
        $cases = array(2, 0, 1, 1, 1, 2);
        return sprintf($titles[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]], $number);
    }

    static function p($data)
    {
        echo '<style>pre{page-break-inside:avoid;display:block;padding:10px;margin:10px;font-size:13px;line-height:1.42857143;color:#333;word-break:break-all;word-wrap:break-word;background-color:#f5f5f5;border:1px solid #ccc;border-radius:4px}</style>';
        echo '<pre>';
        $data ? print_r($data) : var_dump($data);
        echo '</pre>';
    }
}