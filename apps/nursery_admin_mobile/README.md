# GreenLeaf Ops (Admin Mobile)

Flutter staff application for GreenLeaf Nursery operations.  
Consumes the **same** Laravel REST API as Admin Web (`/api/v1`).

## Run

```bash
# API on host machine; Android emulator → 10.0.2.2
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Staff credentials only (customer accounts are rejected after login).

## Test

```bash
flutter test
flutter analyze lib test
```

See `docs/PHASE_19_*.md`.
