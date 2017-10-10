<?php

/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 17.08.2017
 * Time: 15:53
 */
class Import
{
    static function data()
    {
        CModule::IncludeModule('iblock');

        $url = 'https://fridaywear.ru/export/index';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        $data = curl_exec($curl);
        curl_close($curl);

        $data = json_decode($data, true);

        foreach ($data['slider'] as $row) {
            self::addItem(9, $row);
        }
        die;

        foreach ($data['review'] as $row) {
            self::addItem(7, $row);
        }
//        foreach ($data['brand'] as $row) {
//            self::addItem(6, $row);
//        }
        foreach ($data['article'] as $row) {
            self::addItem(8, $row);
        }
        foreach ($data['catalog'] as $row) {
            self::addItem(4, $row);
        }

//        var_dump($data);
    }

    static function addItem($iblockId, $data)
    {
        if ($data['is_category'])
            self::addSection($iblockId, $data);
        else
            self::addElement($iblockId, $data);
    }

    static function addSection($iblockId, $data)
    {
        if (count($data['photos']) > 1) {
            var_dump($data);
            die;
        }

        if (!$data['parent_id'] || !$data['level'] || !$data['url']) return;
        $model = new CIBlockSection();

        $row = $model::GetList([], ['XML_ID' => $data['id'], 'IBLOCK_ID' => $iblockId])->GetNext();
        $params = [
            'NAME' => $data['name'],
            'ACTIVE' => $data['status'] == 1 || $data['status_id'] == 1 ? 'Y' : 'N',
            'IBLOCK_SECTION_ID' => $data['parent_id'] ? CIBlockSection::GetList([], ['XML_ID' => $data['parent_id'], 'IBLOCK_ID' => $iblockId])->GetNext()['ID'] : null,
            'DESCRIPTION' => $data['data']['content'],
        ];
        if (!$row) {
            $params += [
                'XML_ID' => $data['id'],
                'CODE' => $data['url'],
                'PICTURE' => self::getFile($data['photos'], ['main']),
                'IBLOCK_ID' => $iblockId,
                'DESCRIPTION_TYPE' => 'html',
                'DATE_ACTIVE_FROM' => date('d.m.Y', is_numeric($data['created_at']) ? $data['created_at'] : strtotime($data['created_at'])),
                'DATE_CREATE' => date('d.m.Y', is_numeric($data['created_at']) ? $data['created_at'] : strtotime($data['created_at'])),
            ];

            $id = $model->Add($params);
        } else {
            $model->Update($row['ID'], $params);
            return;
        }

        if (!$id) {
            var_dump($params, $model->LAST_ERROR);
            die;
        }
    }

    static function getFile($list, $types)
    {
        $file = current(array_filter($list, function ($row) use ($types) {
            return $row['type'] ? in_array($row['type'], (array)$types) : true;
        }));

        return $file && preg_match('#^.+\.#', $file['name']) ? CFile::MakeFileArray('https://fridaywear.ru/data/_tmp/' . $file['name']) : null;
    }

    static function addElement($iblockId, $data)
    {
        if (count($data['photos']) > 2) {
//            var_dump($data);
//            return;
        }

//        if (!$data['url']) return;
        $model = new CIBlockElement();

        $query = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $iblockId, 'PROPERTY_ID' => 49]);
        while ($row = $query->GetNext()) {
            if ($data['characters'][$row['XML_ID']]) {
                $data['characters'][$row['PROPERTY_CODE']][] = $row['VALUE'];
            }
        }

        $row = $model::GetList([], ['XML_ID' => $data['id'], 'IBLOCK_ID' => $iblockId])->GetNext();
        $params = [
            'ACTIVE' => $data['status'] == 1 || $data['status_id'] == 1 ? 'Y' : 'N',
            'NAME' => $data['name'],
            'IBLOCK_SECTION_ID' => $data['parent_id'] ? CIBlockSection::GetList([], ['XML_ID' => $data['parent_id'], 'IBLOCK_ID' => $iblockId])->GetNext()['ID'] : null,
            'PREVIEW_TEXT' => $data['about'],
            'DETAIL_TEXT' => str_replace(['/data/_source/', '../../source/', '../source/', '/source/'], '/upload/content/', $data['data']['content']),
            'TAGS' => $data['data']['tags'],
            'PROPERTY_VALUES' => $data['characters'],
        ];

//        var_dump($data['characters']);die;

        $query = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId]);
        while ($property = $query->GetNext()) if ($params['PROPERTY_VALUES'][$property['CODE']]) {
            if (is_string($params['PROPERTY_VALUES'][$property['CODE']]) && $property['MULTIPLE'] == 'Y')
                $params['PROPERTY_VALUES'][$property['CODE']] = explode(',', $params['PROPERTY_VALUES'][$property['CODE']]);
            $replaces = [];
            foreach ((array)$params['PROPERTY_VALUES'][$property['CODE']] as $value) {
                // Добавляем элементы списка с подстановкой
                if ($property['PROPERTY_TYPE'] == 'L') {
                    $propertyParams = ['PROPERTY_ID' => $property['ID'], 'VALUE' => $value];
                    if (!($propertyReplace = CIBlockPropertyEnum::GetList([], $propertyParams)->GetNext()['ID']))
                        $propertyReplace = CIBlockPropertyEnum::Add($propertyParams);
                    // Добавляем элементы инфоблока с подстановкой
                } elseif ($property['PROPERTY_TYPE'] == 'E' && is_numeric($value)) {
                    $propertyParams = ['IBLOCK_ID' => $property['LINK_IBLOCK_ID'], 'XML_ID' => $value];
                    $propertyReplace = CIBlockElement::GetList([], $propertyParams)->GetNext()['ID'];
                    if (!$propertyReplace) continue;
                } elseif ($property['PROPERTY_TYPE'] == 'E') {
                    $propertyParams = ['IBLOCK_ID' => $property['LINK_IBLOCK_ID'], 'NAME' => $value];
                    if (!($propertyReplace = CIBlockElement::GetList([], $propertyParams)->GetNext()['ID']))
                        $propertyReplace = (new CIBlockElement())->Add($propertyParams + ['CODE' => CUtil::translit($value, 'ru', ['replace_space' => '-', 'replace_other' => '-'])]);
                    // Добавляем элементы справочника с подстановкой
                } elseif ($property['USER_TYPE'] == 'directory') {
                    $propertyParams = ['UF_NAME' => $value];
                    if (!($propertyReplace = HB::find($property['USER_TYPE_SETTINGS']['TABLE_NAME'], ['filter' => $propertyParams])->fetchRaw()['UF_XML_ID'])) {
                        $propertyReplace = Translit::UrlTranslit($value) ?: CUtil::translit($value, 'ru', ['replace_space' => '-', 'replace_other' => '-']);
                        HB::add($property['USER_TYPE_SETTINGS']['TABLE_NAME'], $propertyParams + [
                                'UF_XML_ID' => $propertyReplace,
                            ]);
                    }

                } else {
                    $propertyReplace = $value;
                }
                $replaces[] = $propertyReplace;
            }

            $params['PROPERTY_VALUES'][$property['CODE']] = is_array($params['PROPERTY_VALUES'][$property['CODE']]) ? $replaces : current($replaces);
        }


        if (!$row) {
            $params += [
                'XML_ID' => $data['id'],
                'CODE' => $data['url'],
                'IBLOCK_ID' => $iblockId,
                'SORT' => $data['lft'] + 500,
                'PREVIEW_PICTURE' => self::getFile($data['photos'], ['main']),
                'PREVIEW_TEXT_TYPE' => 'text',
                'DETAIL_PICTURE' => self::getFile($data['photos'], ['social']) != self::getFile($data['photos'], ['main']) ? self::getFile($data['photos'], ['social']) : '',
                'DETAIL_TEXT_TYPE' => 'html',
                'ACTIVE_DATE_FROM' => date('d.m.Y', is_numeric($data['created_at']) ? $data['created_at'] : strtotime($data['created_at'])),
                'DATE_CREATE' => date('d.m.Y', is_numeric($data['created_at']) ? $data['created_at'] : strtotime($data['created_at'])),
            ];
            $id = $model->Add($params);

            /*$propertyQuery = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId]);
            while ($property = $propertyQuery->GetNext()) {
                if (!empty($data['characters'][$property['CODE']])) {
                    $values = [];
                    foreach (array_diff((array)$data['characters'][$property['CODE']], ['', null, false]) as $value) {
                        if (in_array($property['PROPERTY_TYPE'], ['L'])) {
                            $params = ['PROPERTY_ID' => $property['ID'], 'VALUE' => $value];
                            if ($item = CIBlockPropertyEnum::GetList([], [] + $params)->GetNext())
                                $values[] = $item['ID'];
                            else
                                $values[] = CIBlockPropertyEnum::Add($params + ['XML_ID' => CUtil::translit($value, 'ru')]);
                        }
                    }
                    if (count($values)) $data['characters'][$property['CODE']] = $property['MULTIPLE'] == 'Y' ? $values : implode(',', $values);
                }
            }
            CIBlockElement::SetPropertyValues($id, $iblockId, $data['characters']);*/

            if (!$id) {
                var_dump($params, $model->LAST_ERROR);
                die;
            }
        } else {
            $id = $row['ID'];
            $model->Update($id, $params);
        }

        if ($data['characters']['price']) {
            if (
            !$row['ID']) {
                CCatalogProduct::Add(["ID" => $id, "VAT_INCLUDED" => "Y"]);
                CPrice::Add([
                    "PRODUCT_ID" => $id,
                    "CATALOG_GROUP_ID" => 1,
                    "PRICE" => $data['characters']['price'],
                    "CURRENCY" => "RUB",
                ]);
            }
        }

        if ($data['characters']['sections'] || $data['data']['tags']) {
            $sections = [];
            foreach ($data['characters']['sections'] as $row) {
                $sections[] = CIBlockSection::GetList([], ['XML_ID' => $row['id'], 'IBLOCK_ID' => $iblockId])->GetNext()['ID'];
            }
            foreach (explode(',', $data['data']['tags']) as $row) {
                $sections[] = CIBlockSection::GetList([], ['NAME' => trim($row), 'IBLOCK_ID' => $iblockId])->GetNext()['ID'];
            }
            CIBlockElement::SetElementSection($id, $sections);
        }
    }

    static function test($value = 'привет конфет')
    {
        var_dump(CUtil::translit($value, 'ru', ['replace_space' => '-', 'replace_other' => '-']));
    }
}


class Translit
{
    static function UrlTranslit($string)
    {
        $string = preg_replace("/[_\s\.,?!\[\](){}]+/", "-", $string);
        $string = preg_replace("/-{2,}/", "-", $string);
        $string = preg_replace("/_-+_/", "--", $string);
        $string = preg_replace("/[_\-]+$/", "", $string);
        $string = Translit::Transliterate($string);
        $string = ToLower($string);
        $string = preg_replace("/j{2,}/", "j", $string);
        $string = preg_replace("/[^0-9a-z_\-]+/", "", $string);
        return $string;
    }

    static function Transliterate($string)
    {
        $cyr = array(
            "Щ", "Ш", "Ч", "Ц", "Ю", "Я", "Ж", "А", "Б", "В", "Г", "Д", "Е", "Ё", "З", "И", "Й", "К", "Л", "М", "Н", "О", "П", "Р", "С", "Т", "У", "Ф", "Х", "Ь", "Ы", "Ъ", "Э", "Є", "Ї",
            "щ", "ш", "ч", "ц", "ю", "я", "ж", "а", "б", "в", "г", "д", "е", "ё", "з", "и", "й", "к", "л", "м", "н", "о", "п", "р", "с", "т", "у", "ф", "х", "ь", "ы", "ъ", "э", "є", "ї"
        );
        $lat = array(
            "Shch", "Sh", "Ch", "C", "Ju", "Ja", "Zh", "A", "B", "V", "G", "D", "Je", "Jo", "Z", "I", "J", "K", "L", "M", "N", "O", "P", "R", "S", "T", "U", "F", "Kh", "'", "Y", "`", "E", "Je", "Ji",
            "shch", "sh", "ch", "c", "ju", "ja", "zh", "a", "b", "v", "g", "d", "je", "jo", "z", "i", "j", "k", "l", "m", "n", "o", "p", "r", "s", "t", "u", "f", "kh", "'", "y", "`", "e", "je", "ji"
        );
        for ($i = 0; $i < count($cyr); $i++) {
            $c_cyr = $cyr[$i];
            $c_lat = $lat[$i];
            $string = str_replace($c_cyr, $c_lat, $string);
        }
        $string = preg_replace("/([qwrtpsdfghklzxcvbnmQWRTPSDFGHKLZXCVBNM]+)[jJ]e/", "\${1}e", $string);
        $string = preg_replace("/([qwrtpsdfghklzxcvbnmQWRTPSDFGHKLZXCVBNM]+)[jJ]/", "\${1}'", $string);
        $string = preg_replace("/([eyuioaEYUIOA]+)[Kk]h/", "\${1}h", $string);
        $string = preg_replace("/^kh/", "h", $string);
        $string = preg_replace("/^Kh/", "H", $string);
        return $string;
    }
}