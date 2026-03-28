<?php
// Heading
$_['heading_title']                 = 'Импорт внешних отзывов';

// Text
$_['text_extension']                = 'Расширения';
$_['text_success']                  = 'Импорт завершён: %s';
$_['text_import']                   = 'Загрузка файла импорта';
$_['text_result']                   = 'Результат импорта';
$_['text_total_rows']               = 'Обработано строк';
$_['text_errors']                   = 'Ошибки';
$_['text_warnings']                 = 'Предупреждения';
$_['text_errors_count']             = 'Количество ошибок';
$_['text_inserted_reviews']         = 'Добавлено новых отзывов';
$_['text_updated_reviews']          = 'Обновлено дублей';
$_['text_skipped_duplicates']       = 'Пропущено дублей';
$_['text_total_images']             = 'Всего изображений';
$_['text_inserted_images']          = 'Загружено изображений';
$_['text_deleted_images']           = 'Удалено старых изображений';
$_['text_skipped_images']           = 'Пропущено изображений';
$_['text_import_with_errors']       = 'Импорт выполнен с ошибками. Проверьте отчёт ниже.';

// Entry
$_['entry_file']                    = 'CSV-файл';
$_['entry_images']                  = 'Фотографии отзывов';
$_['entry_options']                 = 'Параметры';
$_['entry_update_duplicates']       = 'Обновить дублирующие записи';

// Help
$_['help_csv']                      = 'CSV-файл одной площадки. Обязательные колонки: key_id, source_code, author_name, rating, text, date_added.';
$_['help_images']                   = 'Необязательно. Имена файлов: key_id_1.jpg, key_id_2.jpg и т.д. За одну загрузку — только одна площадка и только её изображения.';
$_['help_update_duplicates']        = 'Если включено — существующие отзывы по связке source_code + key_id будут полностью обновлены. Старые фотографии таких отзывов удаляются всегда, даже если новые не загружены.';

// Column
$_['column_row']                    = 'Строка';
$_['column_message']                = 'Сообщение';

// Button
$_['button_import']                 = 'Импортировать';
$_['button_cancel']                 = 'Назад';

// Error
$_['error_permission']              = 'У вас нет прав для изменения модуля импорта отзывов!';
$_['error_csv_required']            = 'Не выбран CSV-файл для импорта.';
$_['error_csv_upload']              = 'Ошибка загрузки CSV-файла.';
$_['error_csv_tmp']                 = 'Временный CSV-файл недоступен.';
$_['error_csv_extension']           = 'Допустим только CSV-файл.';
$_['error_image_upload']            = 'Одна из картинок отзыва загружена с ошибкой.';
$_['error_image_extension']         = 'Допустимые форматы изображений: jpg, jpeg, png, webp.';

$_['entry_images_on_server'] = 'Фото уже загружены на сервере с верными key_id';
$_['help_images_on_server']  = 'Если включено — картинки берутся из папки image/catalog/reviews_photos/import_temp/{source_code}/. После завершения импорта эта временная папка очищается.';