# Corpus re-validation (highlights)

Spot-verification of a 47-finding research corpus against the 2026-11-19 tips
(8.4.28 / 8.5.13 / 8.6.0), release Docker images, unmodified corpus PoCs.

| Finding | 8.4.28 | 8.5.13 | 8.6.0 | Signature |
|---|---|---|---|---|
| #1 Exception `::$trace` type confusion in `getTraceAsString` | **SIGSEGV 139** | **SIGSEGV 139** | clean (exit 0) | `TRACE_TYPE=object`, object header walked as HashTable, heap addresses printed as frame indexes (info leak) |
| #6 quoted-printable encode OOB read | **live** | **live** | SP kept (fixed-shape) | 16-byte input → 15-byte output, trailing SP deleted, `TAIL 41414141…`; ASan aborts on debug builds |
| #7 stream_filter_remove UAF | see Finding 3, escalated to `system()` on all three tips | | | |
| #49 open_basedir | see Finding 2, live on all three tips | | | |
| #50 json_encode UAF | inert on release builds with this PoC shape (source-level present; needs grooming) | | | |

## Run

```
docker run --rm -v $PWD:/h php:8.4-cli php /h/poc-f1.php; echo EXIT=$?   # 139
docker run --rm -v $PWD:/h php:8.5-cli php /h/poc-f1.php; echo EXIT=$?   # 139
docker run --rm -v $PWD:/h php:8.6-cli php /h/poc-f1.php; echo EXIT=$?   # 0
docker run --rm -v $PWD:/h php:8.4-cli php /h/poc-f6.php                 # AFTER 15 / TAIL 4141...
```

## Files

- `poc/poc-f1.php`, unserialize Exception-trace confusion (corpus original, unmodified)
- `poc/poc-f6.php`, quoted-printable OOB read (corpus original, unmodified)
