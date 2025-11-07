# 🔧 DNS записи для добавления в хостинг (Mailgun)

## 📋 Все записи для домена `new.backtobase.ca`

---

## ✅ Запись 1: DKIM (krs._domainkey)

**Type:** `TXT`  
**Name:** `krs._domainkey`  
**Value:** `k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAqr2RAheIeJKLsGKngf1/jqhJ/EBYZ8Uj0WsWkkqRIh5qjVaH4XQrBVZcWufabgCjPgIiq0pyF9IneGKVxP1+OgxH9U1daWhCs4ZsiIa3lQTJPuvQjeNfnMfgkrk/AWU84ht9DiIj0JRJmJaxRhXOsW5lGIEXG0B3c2b58v6O3VyNnLehHGrFpeeRxJRmblrYkraZyXjoKZgVZfhgQl+lZdw5ho7WctoyPN5KzzL2tFlsn14yF3Tnf+VJhXGrMi310dMKq+uEtqqV/wUS4PsXDvvRCJ+qvUSLLBCh3STN7WH+TeeYSKOUvoPCS+xsH1JenJLdTGxjVdQKYmtuWSK/swIDAQAB`  
**TTL:** `3600`

---

## ✅ Запись 2: SPF

**Type:** `TXT`  
**Name:** `@` (или оставьте пустым для корневого домена)  
**Value:** `v=spf1 include:mailgun.org ~all`  
**TTL:** `3600`

---

## ✅ Запись 3: MX (mxa.mailgun.org)

**Type:** `MX`  
**Name:** `@` (или оставьте пустым для корневого домена)  
**Priority:** `10` (или `0`, в зависимости от интерфейса)  
**Value:** `mxa.mailgun.org`  
**TTL:** `3600`

---

## ✅ Запись 4: MX (mxb.mailgun.org)

**Type:** `MX`  
**Name:** `@` (или оставьте пустым для корневого домена)  
**Priority:** `10` (или `0`, в зависимости от интерфейса)  
**Value:** `mxb.mailgun.org`  
**TTL:** `3600`

---

## ✅ Запись 5: CNAME (email)

**Type:** `CNAME`  
**Name:** `email`  
**Value:** `mailgun.org`  
**TTL:** `3600`

---

## ✅ Запись 6: DMARC

**Type:** `TXT`  
**Name:** `_dmarc`  
**Value:** `v=DMARC1; p=none; pct=100; fo=1; ri=3600; rua=mailto:ca560f54@dmarc.mailgun.org,mailto:6025edc5@inbox.ondmarc.com; ruf=mailto:ca560f54@dmarc.mailgun.org,mailto:6025edc5@inbox.ondmarc.com;`  
**TTL:** `3600`

---

## 🎯 Пошаговая инструкция для cPanel (GreenGeeks)

### Шаг 1: Войти в cPanel

1. Откройте: `https://new.backtobase.ca:2083` (или ваш URL cPanel)
2. Войдите с вашими учетными данными

---

### Шаг 2: Найти Zone Editor

1. В cPanel найдите раздел **"Domains"** или **"Домены"**
2. Найдите **"Zone Editor"** или **"DNS Zone Editor"**
3. Нажмите на него

---

### Шаг 3: Выбрать домен

1. Выберите домен **`new.backtobase.ca`**
2. Должна открыться таблица с существующими DNS записями

---

### Шаг 4: Добавить каждую запись

**Для каждой записи:**

1. Нажмите **"Add Record"** или **"Add"**
2. Заполните поля (см. ниже для каждой записи)
3. Нажмите **"Add Record"** или **"Save"**

---

## 📝 Детальные инструкции для каждой записи

### Запись 1: DKIM (krs._domainkey)

**В форме добавления записи:**
- **Type:** Выберите `TXT` из выпадающего списка
- **Name:** Введите `krs._domainkey` (без домена!)
- **TTL:** `3600`
- **TXT Data:** Вставьте весь длинный ключ:
  ```
  k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAqr2RAheIeJKLsGKngf1/jqhJ/EBYZ8Uj0WsWkkqRIh5qjVaH4XQrBVZcWufabgCjPgIiq0pyF9IneGKVxP1+OgxH9U1daWhCs4ZsiIa3lQTJPuvQjeNfnMfgkrk/AWU84ht9DiIj0JRJmJaxRhXOsW5lGIEXG0B3c2b58v6O3VyNnLehHGrFpeeRxJRmblrYkraZyXjoKZgVZfhgQl+lZdw5ho7WctoyPN5KzzL2tFlsn14yF3Tnf+VJhXGrMi310dMKq+uEtqqV/wUS4PsXDvvRCJ+qvUSLLBCh3STN7WH+TeeYSKOUvoPCS+xsH1JenJLdTGxjVdQKYmtuWSK/swIDAQAB
  ```

---

### Запись 2: SPF

**В форме добавления записи:**
- **Type:** Выберите `TXT`
- **Name:** Оставьте **пустым** или введите `@` (для корневого домена)
- **TTL:** `3600`
- **TXT Data:** `v=spf1 include:mailgun.org ~all`

---

### Запись 3: MX (mxa.mailgun.org)

**В форме добавления записи:**
- **Type:** Выберите `MX`
- **Name:** Оставьте **пустым** или введите `@` (для корневого домена)
- **TTL:** `3600`
- **Priority:** `10` (или `0`, в зависимости от интерфейса)
- **Value:** `mxa.mailgun.org`

---

### Запись 4: MX (mxb.mailgun.org)

**В форме добавления записи:**
- **Type:** Выберите `MX`
- **Name:** Оставьте **пустым** или введите `@` (для корневого домена)
- **TTL:** `3600`
- **Priority:** `10` (или `0`, в зависимости от интерфейса)
- **Value:** `mxb.mailgun.org`

---

### Запись 5: CNAME (email)

**В форме добавления записи:**
- **Type:** Выберите `CNAME`
- **Name:** Введите `email` (без домена!)
- **TTL:** `3600`
- **Value:** `mailgun.org`

---

### Запись 6: DMARC

**В форме добавления записи:**
- **Type:** Выберите `TXT`
- **Name:** Введите `_dmarc` (без домена!)
- **TTL:** `3600`
- **TXT Data:** `v=DMARC1; p=none; pct=100; fo=1; ri=3600; rua=mailto:ca560f54@dmarc.mailgun.org,mailto:6025edc5@inbox.ondmarc.com; ruf=mailto:ca560f54@dmarc.mailgun.org,mailto:6025edc5@inbox.ondmarc.com;`

---

## ⚠️ Важные моменты

1. **Для корневого домена (`@`):**
   - В поле **Name** оставьте **пустым** или введите `@`
   - Это для SPF и MX записей

2. **Для поддоменов:**
   - В поле **Name** введите только поддомен (без `.new.backtobase.ca`)
   - Например: `krs._domainkey`, `email`, `_dmarc`

3. **DKIM запись очень длинная:**
   - Убедитесь, что скопировали весь ключ полностью
   - Проверьте, что нет разрывов строк

4. **TTL:**
   - Установите `3600` (1 час) для быстрого распространения

---

## ✅ После добавления всех записей

1. **Подождите 5-30 минут** для распространения DNS

2. **Проверьте через MXToolbox:**
   - https://mxtoolbox.com/TXTLookup.aspx
   - Проверьте каждую запись:
     - `krs._domainkey.new.backtobase.ca`
     - `new.backtobase.ca` (SPF)
     - `_dmarc.new.backtobase.ca`

3. **Вернитесь в Mailgun:**
   - Нажмите **"Activate"** для каждой записи
   - Дождитесь верификации (статус изменится на "Verified")

---

## 🔍 Проверка через командную строку (опционально)

**Если хотите проверить через командную строку:**

```bash
# Проверка DKIM
nslookup -type=TXT krs._domainkey.new.backtobase.ca

# Проверка SPF
nslookup -type=TXT new.backtobase.ca

# Проверка DMARC
nslookup -type=TXT _dmarc.new.backtobase.ca

# Проверка MX
nslookup -type=MX new.backtobase.ca

# Проверка CNAME
nslookup -type=CNAME email.new.backtobase.ca
```

---

## 🚀 Итог

**Добавьте все 6 записей в Zone Editor в cPanel:**

1. ✅ DKIM (krs._domainkey) - TXT
2. ✅ SPF (@) - TXT
3. ✅ MX (mxa.mailgun.org) - MX
4. ✅ MX (mxb.mailgun.org) - MX
5. ✅ CNAME (email) - CNAME
6. ✅ DMARC (_dmarc) - TXT

**После добавления подождите 5-30 минут и активируйте в Mailgun!** 🎉



