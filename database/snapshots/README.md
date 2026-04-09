# Снимки текстового контента (CMS)

Тексты главной, карточек комнат и страниц комнат хранятся в **MySQL** (админка пишет в БД, а не в файлы проекта). Чтобы сохранить их **локально и в GitHub**, нужно выгрузить строки из БД в JSON.

## Экспорт

На машине, где `config.php` реально подключается к той же базе, что и сайт (часто это **SSH на хостинге**):

```bash
php export_cms_snapshot.php
```

Скрипт лежит в **корне сайта** (рядом с `config.php`). На Windows с установленным PHP:

```powershell
cd C:\path\to\site
php export_cms_snapshot.php
```

Появятся файлы:

- `cms_content_snapshot.json` — последний снимок (перезаписывается)
- `cms_content_snapshot_<дата-время UTC>.json` — копия на момент экспорта

## Что попадает в снимок

Таблицы, если они есть: `content_settings`, `rooms_settings`, `room_cards_settings`, `wellness_settings`, `floorplan_settings`, `homepage_settings`, `room_pages_settings`, `massage_settings`, `special_settings`, `about_settings`, `retreat_settings` — строка с **`id = 1`**.

## Git

После экспорта закоммитьте файлы в этой папке и сделайте `git push`.

**Не коммитьте** `config.php` с паролями и ключами.
