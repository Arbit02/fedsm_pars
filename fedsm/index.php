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
    preg_match_all('/<li>(.*?)<\/li>/u', $data, $matches);

    $rows = [];

    foreach ($matches[1] as $item) {
        // Очистка и разбиение строки
        $item = trim($item, " \t\n\r\0\x0B;");

        // Разбиваем строку на колонки с использованием регулярных выражений
        preg_match('/(\d+)\.\s+(.*?)\s*,\s*(\d{2}\.\d{2}\.\d{4})?\s*г\.р\.\s*,\s*(.*)/u', $item, $match);
        if (!$match) {
            preg_match('/(\d+)\.\s+(.*?)\,\s*(\d{2}\.\d{2}\.\d{4})?\s*г\.р\.\s*,\s*(.*)/u', $item, $match);
        }

        if ($match) {
            // Извлекаем данные из совпадений
            $number = $match[1];  // Номер
            $full_name = $match[2];  // Полное имя
            $date_of_birth = $match[3] ?? '';  // Дата рождения
            $address = rtrim(trim($match[4]), ';');  // Убираем ; с конца адреса

            // Разбиваем ФИО на части
            $name_parts = preg_split('/\s+/', $full_name);
            $last_name = $name_parts[0] ?? '';
            $first_name = $name_parts[1] ?? '';
            $middle_name = str_replace(['*', ','], '', ($name_parts[2] ?? ''));  // Убираем * и , из отчества

            // Проверяем наличие второго ФИО в скобках
            preg_match('/\((.*?)\)/u', $full_name, $bracket_match);
            if ($bracket_match) {
                // Разбиваем второе ФИО на части
                $second_full_name = trim($bracket_match[1]);
                $second_name_parts = preg_split('/\s+/', $second_full_name);
                $last_name2 = $second_name_parts[0] ?? '';
                $first_name2 = $second_name_parts[1] ?? '';
                $middle_name2 = str_replace(['*', ','], '', ($second_name_parts[2] ?? ''));  // Убираем * и , из отчества
            } else {
                $last_name2 = $first_name2 = $middle_name2 = '';
            }

            // Добавляем данные в массив строк
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
    }

    // Сохраняем данные в CSV
    $fp = fopen(__DIR__ . '/files/individuals.csv', "w");

    // Записываем BOM для корректного отображения UTF-8
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Записываем заголовки
    fputcsv($fp, ['Номер', 'Фамилия', 'Имя', 'Отчество', 'Фамилия2', 'Имя2', 'Отчество2', 'Дата рождения', 'Адрес']);

    // Записываем строки данных
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }

    fclose($fp);
}
function Work_With_File_Organizations($data) {
    // Определяем строки в HTML
    preg_match_all('/<li>(.*?)<\/li>/u', $data, $matches);

    $rows = [];

    foreach ($matches[1] as $item) {
        // Определяем строку по колонкам
        $columns = parseColumns_Org($item);

        if ($columns) {
            $rows[] = $columns;
        }
    }

    // Сохраняем данные в CSV
    $fp = fopen(__DIR__ . '/files/organizations.csv', "w");

    // Записываем BOM для корректного отображения UTF-8
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Записываем заголовки
    fputcsv($fp, ['Номер', 'Наименование', 'ИНН', 'ОГРН']);

    // Записываем строки данных
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }

    fclose($fp);
}

function parseColumns_Org($item) {
    // Разделяем строку на части
    $columns = [];

    // Ищем номер
    if (preg_match('/(\d+)\.\s+/', $item, $match)) {
        $columns[] = $match[1];
        $item = str_replace($match[0], '', $item);
    } else {
        return false;
    }

    // Ищем наименование до первой запятой и убираем символы '*'
    if (preg_match('/([^,]+),?\s*(?:,\s+ИНН:\s*(\d{10,12}))?(?:,\s+ОГРН:\s*(\d{13}))?;/u', $item, $match)) {
        // Убираем символы '*'
        $name = str_replace('*', '', trim($match[1]));
        $columns[] = $name;
        $columns[] = $match[2] ?? '';
        $columns[] = $match[3] ?? '';
    } else {
        return false;
    }

    return $columns;
}

Work_With_File_Organizations(Get_Content_Organization($text)[0]);
Work_With_File_Persons(Get_Content_Individ_Person($text)[0]);
