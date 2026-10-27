# com_thapi – API strony publicznej

Wszystkie zapytania są wysyłane do `index.php` witryny Joomla:

```
/index.php?option=com_thapi&format=json&task=api.<metoda>
```

Odpowiedzi są zwracane w formacie JSON (UTF-8, bez escapowania polskich znaków).

## Nowe / rozszerzone metody

### `api.getLatestArticles`

Najnowsze opublikowane artykuły – używane przez sekcję „Najnowsze wpisy” na stronie głównej.

Parametry (wszystkie opcjonalne):

| Parametr | Domyślnie | Opis |
|---|---|---|
| `limit` | `5` | Liczba artykułów (1–24) |
| `offset` | `0` | Przesunięcie |
| `catid` | – | ID jednej kategorii |
| `categories` | – | Lista ID kategorii rozdzielona przecinkami |
| `includeChildren` | `0` | `1` = uwzględnij podkategorie |
| `featured` | – | `1` / `0` – tylko wyróżnione / tylko zwykłe |
| `exclude` | – | ID artykułów do pominięcia (CSV) |

Przykład:

```
/index.php?option=com_thapi&format=json&task=api.getLatestArticles&limit=5
```

Odpowiedź:

```json
{
  "articles": [
    {
      "id": 159,
      "title": "Rewolucja w powietrzu…",
      "alias": "159-rewolucja-w-powietrzu…",
      "catid": 8,
      "category_title": "Aktualności",
      "introtext": "…",
      "excerpt": "…",
      "image_intro": "https://klastry.org/images/…jpg",
      "image_intro_alt": "…",
      "created": "2026-10-18 10:00:00",
      "author": "Redakcja",
      "hits": 128,
      "featured": 0,
      "url": "/events/159-rewolucja-w-powietrzu…",
      "category_url": "/events"
    }
  ],
  "count": 1,
  "limit": 5,
  "offset": 0
}
```

Uwagi:

- Zwracane są wyłącznie artykuły opublikowane (`state = 1`), publiczne (`access = 1`), z opublikowaną kategorią i mieszczące się w oknie publikacji.
- `introtext` i `excerpt` są czystym tekstem (bez HTML, z odkodowanymi encjami).
- `url` / `category_url` są generowane przez router Joomla (`Route::_`), więc respektują SEF.
- Obrazki z pola `images` są normalizowane, w tym format `#joomlaImage://`.

### `api.getMenuItems`

Elementy dowolnego menu wraz z gotowymi adresami.

| Parametr | Domyślnie | Opis |
|---|---|---|
| `menutype` | `mainmenu` | Identyfikator menu (`menutype`) |
| `limit` | `20` | Liczba pozycji (1–100) |
| `topLevel` | `0` | `1` = tylko poziom 1 |

### `api.getRecommendedLinks`

Linki polecanych stron – domyślnie z menu `mnu-polecamy`. Zwraca tylko pozycje z poprawnym adresem URL.

Dla znanych aliasów (`mnu-polecamy`, `mnu_polecamy`, `polecamy`, `polecane`) API sprawdza oba warianty separatora i zwraca pierwszy niepusty wynik.

| Parametr | Domyślnie | Opis |
|---|---|---|
| `menutype` | `mnu-polecamy` | Identyfikator menu |
| `limit` | `10` | Liczba pozycji (1–50) |

Przykład:

```
/index.php?option=com_thapi&format=json&task=api.getRecommendedLinks&menutype=mnu-polecamy
```

Odpowiedź:

```json
{
  "menu_type": "mnu-polecamy",
  "requested_menu_type": "mnu-polecamy",
  "menu_items": [
    {
      "id": 101,
      "title": "Otwarta edukacja",
      "url": "http://www.otwartaedukacja.pl",
      "target": "_blank",
      "level": 1,
      "image": null
    }
  ],
  "count": 1
}
```

### `api.getMenuTypes`

Lista wszystkich menutype'ów w witrynie z liczbą pozycji. Przydatna do diagnozy, gdy nazwa menu w Joomla różni się od oczekiwanej.

```
/index.php?option=com_thapi&format=json&task=api.getMenuTypes
```

```json
{
  "menu_types": [
    { "menutype": "main-menu", "total": 4, "published": 4, "site": 4 },
    { "menutype": "mnu-polecamy", "total": 3, "published": 3, "site": 3 }
  ],
  "count": 2
}
```

### `api.getMainMenu`

Zachowuje zgodność z poprzednią wersją (`menu_type`, `menu_items`), ale obsługuje teraz parametr `menutype` oraz zwraca rozszerzone pola pozycji (URL, target, obrazek, klasa CSS). Domyślny `mainmenu` jest zastępowany przez pierwszy niepusty wariant: `main-menu`, `main_menu`, `menu` itd.

### `api.getFeaturedArticle`

Poprawiona wersja: uwzględnia daty publikacji, dostęp publiczny, pole `images`, buduje `url` i `category_url`, a `catid` może być przekazany parametrem lub wzięty z konfiguracji komponentu. Gdy kategoria nie jest ustawiona, zwracany jest najnowszy artykuł z całej witryny.

## Frontend (szablon klastry26)

Klient API znajduje się w `www-klastry/src/api/thapi.ts`. Adres bazowy jest wyznaczany w `src/configs/config.ts`:

- w Joomla: `window.__KLASTRO_BASE__` ustawiany w `templates/klastry26/index.tmpl`,
- lokalnie: `NEXT_PUBLIC_API_URL` (np. `http://localhost:8081/index.php`).

Wykorzystywane metody:

- `fetchLatestArticles(limit)` → `api.getLatestArticles`,
- `fetchRecommendedLinks('mnu-polecamy', limit)` → `api.getRecommendedLinks` (z fallbackiem na `mnu_polecamy`).
