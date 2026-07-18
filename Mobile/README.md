# SignageFlow Mobile

Flutter Android/iOS app for SignageFlow My Tasks and task expenses.

## Configuration

Create `Mobile/.env` from `Mobile/.env.example`:

```env
API_BASE_URL=https://your-production-signageflow-domain.com
```

Do not include a trailing slash. The app calls Laravel under `/api/mobile`.

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
