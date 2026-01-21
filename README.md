# Laravel External API Error Mapping

Ez a projekt egy rövid, célzott gyakorló / portfólió projekt, amely azt mutatja be,
hogyan lehet egy nem megbízható külső API-t egységes, stabil és jól dokumentált
hiba-szerződéssel (error contract) kezelni Laravel 11-ben.

A fókusz nem az UI-n vagy az üzleti logikán van, hanem a defenzív backend tervezésen.

---

## Projekt célja

Valós projektekben a külső API-k:
- timeoutolhatnak
- rate limitelhetnek
- 4xx / 5xx hibákat adhatnak vissza
- vagy hálózati hibával elérhetetlenné válhatnak

A cél egy olyan réteg kialakítása, amely:

- nem engedi szétcsúszni az API válaszokat
- egységes error contractot ad vissza minden esetben
- világosan jelzi, hogy egy hiba retryelhető-e
- elkülöníti az upstream és a saját rendszer hibáit

---

## Megoldás áttekintése

A projekt egy központi UpstreamErrorMapper osztályt használ, amely:

- HTTP response alapú hibákat kezel (4xx / 5xx)
- hálózati és timeout kivételeket kezel (Throwable)
- minden hibát egységes JSON struktúrára képez le

### Egységes error contract példa

ok: false
error:
  code: UPSTREAM_RATE_LIMITED
  message: A külső szolgáltató túl sok kérést kapott (rate limit).
  http_status: 502
  retryable: true
  meta:
    upstream_status: 429
    retry_after: 30

---

## Kezelt hibatípusok

### Upstream HTTP hibák
- 401 → UPSTREAM_UNAUTHORIZED
- 403 → UPSTREAM_FORBIDDEN
- 404 → UPSTREAM_NOT_FOUND
- 422 → UPSTREAM_VALIDATION_FAILED
- 429 → UPSTREAM_RATE_LIMITED
- 5xx → UPSTREAM_UNAVAILABLE, UPSTREAM_TIMEOUT, UPSTREAM_SERVER_ERROR

### Hálózati / runtime hibák
- Timeout → UPSTREAM_TIMEOUT
- Connection / DNS hiba → UPSTREAM_CONNECTION_FAILED
- Egyéb kivétel → UPSTREAM_UNEXPECTED_EXCEPTION

Minden hiba esetén:
- az API 502 Bad Gateway státusszal válaszol
- a kliens a retryable flag alapján dönthet az újrapróbálásról

---

## Tesztelés

A projekt feature teszteket tartalmaz Http::fake() használatával.

Tesztelt esetek:
- upstream 422 validációs hiba
- upstream 429 rate limit Retry-After headerrel
- upstream 503 unavailable
- timeout kivétel
- connection kivétel

Teszt futtatása:
php artisan test

---

## Simulate endpointok (csak fejlesztéshez)

Fejlesztési környezetben (APP_ENV=local) elérhetők
külön simulate route-ok, amelyekkel kézzel is kipróbálható a mapping.

Példák:
- /api/external/simulate/422
- /api/external/simulate/429
- /api/external/simulate/503
- /api/external/simulate-timeout
- /api/external/simulate-connection

Ezek nem éles használatra készültek, kizárólag tanulási és tesztelési célokat szolgálnak.

---

## Futtatás

composer install
php artisan serve

Demo endpoint:
GET /api/external/demo

---

## Mit demonstrál ez a projekt?

- defenzív backend gondolkodás
- külső API hibák strukturált kezelése
- stabil API error contract
- retry policy előkészítése
- Laravel 11 specifikus routing és tesztelés
- nem tutorial-alapú, hanem problémaközpontú megoldás

---

## Megjegyzés

Ez egy szándékosan kisméretű, fókuszált projekt.
A cél nem egy teljes rendszer építése, hanem egy kritikus backend probléma
tiszta, tesztelt megoldásának bemutatása.