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
    preg_match_all('/(.*?)<\/li>/', $data, $matches);

    $rows = [];

    foreach ($matches[1] as $item) {
        preg_match('/(\d+)\.\s+(.*?)\*\s*,\s*(\d{2}\.\d{2}\.\d{4})?\s*г\.р\.\s*,\s*(.*)/u', trim($item), $match);
        if (!$match) {
            preg_match('/(\d+)\.\s+(.*?)\,\s*(\d{2}\.\d{2}\.\d{4})?\s*г\.р\.\s*,\s*(.*)/u', trim($item), $match);
        }
        if ($match) {
            $number = $match[1];  // Номер
            $full_name = $match[2];  // Полное имя
            $date_of_birth = $match[3] ?? '';  // Дата рождения
            $address = rtrim(trim($match[4]), ';');  // Убираем ; с конца адреса

            // Разбиваем ФИО на три части
            $name_parts = preg_split('/\s+/', $full_name);
            $last_name = $name_parts[0] ?? '';
            $first_name = $name_parts[1] ?? '';
            $middle_name = str_replace(['*', ','], '', ($name_parts[2] ?? ''));  // Убираем * и , из отчества

            preg_match('/\((.*?)\)/u', $full_name, $bracket_match);
            if ($bracket_match) {
                // Разбиваем второе ФИО на три части
                $second_full_name = trim($bracket_match[1]);
                $second_name_parts = preg_split('/\s+/', $second_full_name);
                $last_name2 = $second_name_parts[0] ?? '';
                $first_name2 = $second_name_parts[1] ?? '';
                $middle_name2 = str_replace(['*', ','], '', ($second_name_parts[2] ?? ''));  // Убираем * и , из отчества
            } else {
                $last_name2 = $first_name2 = $middle_name2 = '';
            }
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

    $fp = fopen(__DIR__ . '/files/' . "individuals.csv", "w");

    //Ох уж эта кодировка . . .
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Заголовочки
    fputcsv($fp, ['Номер', 'Фамилия', 'Имя', 'Отчество', 'Фамилия2', 'Имя2', 'Отчество2', 'Дата рождения', 'Адрес']);

    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
}
function Work_With_File_Organizations($data) {
    preg_match_all('/<li>(.*?)<\/li>/u', $data, $matches);

    $rows = [];

    foreach ($matches[1] as $item) {
        $number = '';
        $name = '';
        $inn = '';
        $ogrn = '';
        preg_match('/(\d+)\.\s+(.+?)(?:,\s+ИНН:\s*(?:(\d{10,12})|))?(?:,\s+ОГРН:\s*(?:(\d{13})|))?;/u', trim($item), $match);
        if ($match) {
            $number = $match[1];  // Номер
            $name = trim($match[2]);  // Наименование
            $inn = $match[3] ?? '';  // ИНН
            $ogrn = $match[4] ?? '';  // ОГРН

            $rows[] = [$number, $name, $inn, $ogrn];
        }
    }

    $fp = fopen( __DIR__ . '/files/' . "organizations.csv", "w");

    // Опять кодировка
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($fp, ['Номер', 'Наименование', 'ИНН', 'ОГРН']);

    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }    fclose($fp);
}

Work_With_File_Organizations(Get_Content_Organization($text)[0]);
Work_With_File_Persons(Get_Content_Individ_Person($text)[0]);
