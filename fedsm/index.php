<?php
function GetURL($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
// Добавляем заголовок User-Agent
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36'
    ]);
    $html = curl_exec($ch);
    if (curl_errno($ch)) {
        $data = 'cURL Error: ' . curl_error($ch);
    } else {
        $data =  $html;
    }
    curl_close($ch);
    return $data;

}

function Get_Content_Organization($data)
{
    preg_match_all('/<a data-toggle="collapse" href="#russianUL">Организации<\/a>.*?<ol class="terrorist-list">(.*?)<\/ol>/s', $data, $url_matches);
    return $url_matches[1];

}

function Get_Content_Individ_Person($data)
{
    preg_match_all('/<a data-toggle="collapse" href="#russianFL">Физические лица<\/a>.*?<ol class="terrorist-list">(.*)<\/ol>/s', $data, $url_matches);
    return $url_matches[1];

}

$text = GetURL('https://www.fedsfm.ru/documents/terrorists-catalog-portal-act');
function Work_With_File_Persons($data)
{
    // Определяем строки в HTML
    preg_match_all('/<li>(.*?)<\/li>/us', $data, $matches);

    $rows = [];

    foreach ($matches[1] as $item) {
        // Очистка строки
        $item = trim($item, " \t\n\r\0\x0B;");

        // Ищем второе ФИО в скобках
        $second_full_name = '';
        if (preg_match('/\(([^)]+)\)/', $item, $second_match)) {
            $second_full_name = $second_match[1];
            $item = str_replace($second_match[0], '', $item); // Удаляем второе ФИО из строки
        }

        // Разбиваем строку на части
        $parts = preg_split('/,\s*/', $item);

        // Извлекаем номер и полное имя
        if (preg_match('/(\d+)\.\s+(.+)/u', $parts[0], $name_match)) {
            $number = $name_match[1];
            $full_name = $name_match[2];
        } else {
            continue;
        }

        // Разбиваем полное ФИО на части
        $name_parts = preg_split('/\s+/', trim($full_name));
        $last_name = $name_parts[0] ?? '';
        $first_name = $name_parts[1] ?? '';
        $middle_name = str_replace(['*', ','], '', ($name_parts[2] ?? ''));

        // Аналогично :/
        $last_name2 = $first_name2 = $middle_name2 = '';
        if ($second_full_name) {
            $second_name_parts = preg_split('/\s+/', trim($second_full_name));
            $last_name2 = str_replace(';', '', $second_name_parts[0] ?? '');
            $first_name2 = str_replace(';', '', $second_name_parts[1] ?? '');
            $middle_name2 = str_replace(['*', ',', ';'], '', ($second_name_parts[2] ?? ''));
        }

        // Удаляем номер и полное имя из частей
        unset($parts[0]);

        // Берем дату рождения, если есть :)
        $date_of_birth = '';
        foreach ($parts as $key => $part) {
            if (preg_match('/\d{2}\.\d{2}\.\d{4}/', $part, $date_match)) {
                $date_of_birth = $date_match[0];
                unset($parts[$key]); // Удаляем дату из частей
                break;
            }
        }

        // Извлекаем адрес и убираем запятую
        $address = implode(' ', $parts);
        
        $rows[] = [
            $number,
            $last_name,
            $first_name,
            $middle_name,
            $last_name2,
            $first_name2,
            $middle_name2,
            $date_of_birth,
            $address
        ];
    }
    
    $fp = fopen(__DIR__ . '/files/individuals.csv', "w");
    
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Записываем заголовки
    fputcsv($fp, ['Номер', 'Фамилия', 'Имя', 'Отчество', 'Фамилия2', 'Имя2', 'Отчество2', 'Дата рождения', 'Адрес']);
    
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }

    fclose($fp);
}

function Work_With_File_Organizations($data) {
    preg_match_all('/<li>(.*?)<\/li>/us', $data, $matches);
    $fp = fopen(__DIR__ . '/files/organizations.csv', "w");

    
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

    
    fputcsv($fp, ['Номер', 'Наименование', 'ИНН', 'ОГРН']);

    foreach ($matches[1] as $item) {
        // Удаляем всё-всё лишнее из строки
        $item = rtrim($item, ',;* ');

        
        $parts = preg_split('/,\s*/', $item);
        
        if (preg_match('/^(\d+)\.\s*(.+)/u', $parts[0], $match)) {
            $number = $match[1];
            $name = $match[2];
        } else {
            continue; 
        }

        
        $inn = '';
        $ogrn = '';

        // Проверяем наличие ИНН и ОГРН
        foreach ($parts as $part) {
            if (strpos($part, 'ИНН:') !== false) {
                $inn = trim(str_replace('ИНН:', '', $part));
            }
            if (strpos($part, 'ОГРН:') !== false) {
                $ogrn = trim(str_replace('ОГРН:', '', $part));
            }
        }
        
        fputcsv($fp, [$number, $name, $inn, $ogrn]);
    }

    fclose($fp);
}

Work_With_File_Organizations(Get_Content_Organization($text)[0]);
Work_With_File_Persons(Get_Content_Individ_Person($text)[0]);
