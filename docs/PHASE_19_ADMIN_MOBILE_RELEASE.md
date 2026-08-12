# PHASE 19 — Admin Mobile Release

## Build

```bash
cd apps/nursery_admin_mobile

# Android debug
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1

# Android release (must use HTTPS)
flutter build apk --release \
  --dart-define=API_BASE_URL=https://api.your-domain.com/api/v1
```

## Identity

| | Value |
|--|--|
| Android label | GreenLeaf Ops |
| iOS display name | GreenLeaf Ops |
| Application ID | `com.greenleaf.nursery_admin_mobile` (flutter create default) |

## Checklist

- [ ] Production HTTPS API  
- [ ] Distinct launcher icon vs customer app (replace default Flutter icon before store)  
- [ ] Camera permission copy reviewed  
- [ ] No signing keys in git  
- [ ] Staff smoke test on device Wi-Fi + cellular  

Do not publish automatically from this phase.
