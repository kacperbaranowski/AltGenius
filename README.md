# AltGenius - AI-Powered ALT Text Generator for WordPress

![Version](https://img.shields.io/badge/version-1.0.5-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-green.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-orange.svg)

**AltGenius** to zaawansowana wtyczka WordPress, która automatycznie generuje teksty alternatywne (ALT) dla obrazów w mediach za pomocą sztucznej inteligencji OpenAI (ChatGPT). Wtyczka znacząco poprawia dostępność strony oraz SEO poprzez inteligentne opisywanie obrazów.

## 🚀 Główne Funkcje

### 🤖 Automatyczne Generowanie ALT

- **Generowanie z AI:** Wykorzystuje modele GPT do tworzenia dokładnych, kontekstowych opisów obrazów
- **Wsparcie dla wielu modeli:** gpt-5.1, gpt-5-mini, gpt-4.1, gpt-4o, o3, o4-mini i więcej
- **Kontekst treści:** Automatyczne uwzględnianie kontekstu wpisu/strony produktu
- **Vision API:** Bezpośrednia analiza obrazu (base64) zamiast URL

### 📊 Panel Statystyk i Logów

- **Dedykowane menu top-level** w WordPress
- **Statystyki w czasie rzeczywistym:**
  - Wszystkie obrazy w bibliotece
  - Obrazy z ALT
  - Obrazy bez ALT
  - Procent pokrycia
- **Lista obrazków bez ALT** z miniaturami i szybkim edytowaniem
- **System logowania** do pliku `logs/alt-scan-log.txt`
- **Podgląd logów** z ostatnich 100 operacji

### ⚡ Akcje Masowe

- **Generuj dla zaznaczonych** - przetwarzanie wybranych obrazów
- **Generuj dla wszystkich bez ALT** - automatyczne przetwarzanie wszystkich brakujących
- **Progress tracking** - monitorowanie postępu w czasie rzeczywistym
- **Batch processing** - przetwarzanie po 10 obrazów na iterację (optymalizacja API)

### ⏰ Automatyzacja (Cron)

- **Cron job** uruchamiany codziennie
- **Automatyczne skanowanie** i generowanie ALT dla nowych obrazów
- **Konfigurowalne limity** (domyślnie 50 obrazów na iterację)
- **Szczegółowe logowanie** wszystkich operacji

### 🔄 Automatyczne Aktualizacje

- **Integracja z GitHub Releases** - automatyczne pobieranie aktualizacji
- **Bezpieczne aktualizacje** - zachowanie ustawień i logów
- **Wersjonowanie** - zgodne z semantic versioning

## 📦 Instalacja

### Metoda 1: Przez panel WordPress

1. Pobierz najnowszą wersję z [GitHub Releases](https://github.com/kacperbaranowski/AltGenius/releases)
2. Przejdź do **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**
3. Wybierz pobrany plik ZIP
4. Kliknij **Instaluj teraz**
5. Aktywuj wtyczkę

### Metoda 2: Manualna instalacja

1. Pobierz wtyczkę z [GitHub](https://github.com/kacperbaranowski/AltGenius)
2. Wypakuj folder do `/wp-content/plugins/`
3. Aktywuj wtyczkę w panelu WordPress

### Metoda 3: Git (dla deweloperów)

```bash
cd wp-content/plugins/
git clone https://github.com/kacperbaranowski/AltGenius.git
```

## ⚙️ Konfiguracja

### 1. Uzyskaj API Key OpenAI

1. Przejdź do [platform.openai.com](https://platform.openai.com/api-keys)
2. Zaloguj się lub utwórz konto
3. Utwórz nowy API key
4. Skopiuj klucz (zachowaj w bezpiecznym miejscu!)

### 2. Konfiguracja wtyczki

1. W WordPress przejdź do **ALT Generator → Ustawienia**
2. Wklej **API Key** w odpowiednie pole
3. Wybierz **Model** (zalecany: `gpt-4o-mini` dla najlepszego stosunku ceny do jakości)
4. (Opcjonalnie) Dostosuj **Prompt** do swoich potrzeb
5. (Opcjonalnie) Włącz **Automatyczne generowanie przy uploadzie**
6. Kliknij **Zapisz zmiany**

## 📖 Użycie

### Generowanie ALT dla pojedynczego obrazu

1. Przejdź do **Media → Biblioteka**
2. Znajdź obraz bez ALT (kolumna "ALT (ChatGPT)" pokazuje "brak")
3. Kliknij przycisk **Generuj ALT**
4. Poczekaj na wygenerowanie (status pojawi się obok przycisku)

### Masowe generowanie ALT

#### Dla zaznaczonych obrazów:

1. Przejdź do **Media → Biblioteka**
2. Zaznacz obrazy (checkbox obok miniatur)
3. Z menu **Akcje masowe** wybierz **Generuj ALT dla zaznaczonych**
4. Kliknij **Zastosuj**

#### Dla wszystkich bez ALT (Panel statystyk):

1. Przejdź do **ALT Generator → Statystyki i Logi**
2. Sprawdź kartę "Bez ALT" - ile obrazów wymaga przetworzenia
3. Kliknij przycisk **⚡ Generuj dla wszystkich bez ALT**
4. Potwierdź w oknie dialogowym
5. Obserwuj postęp w czasie rzeczywistym

### Przeglądanie statystyk i logów

1. Przejdź do **ALT Generator → Statystyki i Logi**
2. Sprawdź:
   - **Card-y ze statystykami:** Wszystkie obrazy, Z ALT, Bez ALT, Pokrycie %
   - **Listę obrazków bez ALT:** Pierwsze 20 z miniaturami
   - **Logi:** Ostatnie 100 operacji
3. Użyj przycisków:
   - **🔍 Skanuj teraz** - odświeża statystyki
   - **🔄 Odśwież** - przeładowuje logi
   - **🗑️ Wyczyść logi** - usuwa wszystkie logi

### Testowanie Crona

Aby ręcznie uruchomić cron job (wymaga WP-CLI):

```bash
wp cron event run altgpt_cron_scan
```

Lub użyj pluginu [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) do testowania.

## 🎨 Dostosowywanie

### Zmiana Promptu

Domyślny prompt:

```
Opisz to zdjęcie jednym zdaniem po polsku do ALT. URL: {{image_url}}
```

Możesz go dostosować w **ALT Generator → Ustawienia → Prompt**. Użyj `{{image_url}}` jako placeholdera.

Przykłady:

```
Stwórz krótki, opisowy alt text dla tego obrazu: {{image_url}}
```

```
Wygeneruj alt text zgodny z WCAG 2.1 dla: {{image_url}}
```

### Zmiana częstotliwości Crona

W pliku wtyczki znajdź (linia ~557):

```php
wp_schedule_event(time(), 'daily', 'altgpt_cron_scan');
```

Zmień `'daily'` na:

- `'hourly'` - co godzinę
- `'twicedaily'` - dwa razy dziennie
- `'daily'` - raz dziennie
- `'weekly'` - raz w tygodniu

### Zmiana limitu przetwarzania

W ustawieniach wtyczki (linia ~72):

```php
'scan_limit' => 50
```

Zwiększ lub zmniejsz wartość według potrzeb (uwaga na koszty API!).

## 🗂️ Struktura Plików

```
wp-alt-generator/
├── wp-alt-generator.php    # Główny plik wtyczki
├── assets/
│   ├── altgpt.js           # JS dla biblioteki mediów
│   ├── stats.js            # JS dla panelu statystyk
│   └── stats.css           # Style dla panelu statystyk
├── logs/
│   ├── .htaccess           # Ochrona katalogu
│   └── alt-scan-log.txt    # Plik logów (tworzony automatycznie)
└── README.md
```

## 🔐 Bezpieczeństwo

- **API Key:** Przechowywany bezpiecznie w bazie danych WordPress
- **Logi chronione:** Folder `logs/` zabezpieczony przez `.htaccess`
- **Nonce verification:** Wszystkie akcje AJAX zabezpieczone
- **Capability checks:** Tylko administratorzy mają dostęp

## 💰 Koszty API OpenAI

Wtyczka używa **Vision API** (analiza obrazu), co ma wpływ na koszty:

### Przykładowe koszty (gpt-4o-mini):

- **Koszt za obraz:** ~$0.001 - $0.003 (zależy od rozmiaru)
- **100 obrazów:** ~$0.10 - $0.30
- **1000 obrazów:** ~$1.00 - $3.00

💡 **Wskazówka:** Użyj `gpt-4o-mini` dla najniższych kosztów z zachowaniem dobrej jakości!

Sprawdź aktualne ceny na [OpenAI Pricing](https://openai.com/api/pricing/).

## 🐛 Rozwiązywanie Problemów

### "Brak API key"

- Upewnij się, że API key jest poprawnie wklejony w ustawieniach
- Sprawdź czy nie ma dodatkowych spacji

### "OpenAI error 401"

- API key jest nieprawidłowy lub wygasł
- Wygeneruj nowy klucz na platform.openai.com

### "OpenAI error 429"

- Przekroczono limit zapytań API
- Poczekaj chwilę lub zwiększ plan na OpenAI
- Zmniejsz limit przetwarzania w opcjach

### Cron nie działa

- Sprawdź czy WordPress Cron jest aktywny (`wp cron event list`)
- Użyj WP Crontrol do debugowania
- Sprawdź logi w **ALT Generator → Statystyki i Logi**

### "processed: 0" przy generowaniu masowym

- Sprawdź czy obrazy rzeczywiście nie mają ALT
- Zobacz logi - mogą zawierać błędy API
- Zweryfikuj permissions w bazie danych

## 🤝 Wsparcie i Zgłaszanie Błędów

- **Issues:** [GitHub Issues](https://github.com/kacperbaranowski/AltGenius/issues)
- **Autor:** Hedea - Kacper Baranowski
- **Email:** [kontakt przez GitHub]

## 📝 Changelog

### v1.0.5 (2026-01-28)

- ✨ Dodano panel statystyk i logów
- ✨ Dodano cron job do automatycznego skanowania
- ✨ Dodano system logowania do pliku
- ✨ Dodano dedykowane menu top-level
- 🐛 Naprawiono pobieranie obrazków bez ALT (SQL query)
- 🔧 Usunięto opcje GitHub z UI (hardcoded)

### v1.0.1

- ✨ Pierwsza publiczna wersja
- ⚡ Vision API (base64 image upload)
- 🎨 Wsparcie dla wielu modeli GPT
- 📦 Akcje masowe w bibliotece mediów
- 🔄 Automatyczne aktualizacje z GitHub

## 📄 Licencja

GPLv2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

---

**Czy ta wtyczka była pomocna? Zostaw ⭐ na [GitHub](https://github.com/kacperbaranowski/AltGenius)!**
