# SignageFlow Mobile

Flutter Android/iOS app for SignageFlow My Tasks and task expenses.

## Configuration

Create `Mobile/.env` from `Mobile/.env.example`:

```env
API_BASE_URL=https://your-production-signageflow-domain.com
```

Do not include a trailing slash. The app calls Laravel under `/api/mobile`.

For Firebase Cloud Messaging, add the platform config files before release:

- `Mobile/android/app/google-services.json`
- `Mobile/ios/Runner/GoogleService-Info.plist`

The app initializes Firebase defensively, so development builds still run while these files are missing.

## Run

```bash
flutter pub get
flutter run
```

## Build

```bash
flutter build apk --release
flutter build ios --release
```

## Launcher Icons

Launcher icons are generated from:

- `assets/images/logo-b.png`
- `assets/images/logo-w.png`

Regenerate them after logo changes:

```bash
dart run flutter_launcher_icons
```
