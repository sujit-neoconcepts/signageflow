import 'dart:async';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';

import '../app_config.dart';
import '../network/api_client.dart';

@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
}

class NotificationService {
  NotificationService._();

  static final instance = NotificationService._();
  late final FirebaseMessaging _messaging;
  String? _token;
  bool _isReady = false;

  final _onMessageController = StreamController<RemoteMessage>.broadcast();

  String? get token => _token;
  Stream<RemoteMessage> get onMessage => _onMessageController.stream;

  Future<void> initialize() async {
    try {
      await Firebase.initializeApp();
      _messaging = FirebaseMessaging.instance;
      FirebaseMessaging.onBackgroundMessage(
        _firebaseMessagingBackgroundHandler,
      );
      await _messaging.requestPermission(alert: true, badge: true, sound: true);
      _token = await _messaging.getToken();
      _messaging.onTokenRefresh.listen((token) {
        _token = token;
        _syncStoredToken();
      });
      FirebaseMessaging.onMessage.listen((message) {
        _onMessageController.add(message);
      });
      _isReady = true;
    } catch (error) {
      debugPrint('Firebase Messaging not configured: $error');
    }
  }

  Future<void> sync(ApiClient api) async {
    if (!_isReady) return;
    _token ??= await _messaging.getToken();
    await _syncToken(api, _token);
  }

  Future<void> _syncStoredToken() async {
    final token = await appStorage.read(key: 'token');
    if (token == null || apiBaseUrl.isEmpty) return;
    await _syncToken(ApiClient(apiBaseUrl, token), _token);
  }

  Future<void> _syncToken(ApiClient api, String? token) async {
    if (token == null || token.isEmpty) return;
    try {
      await api.postJson('/fcm-token', {
        'token': token,
        'platform': Platform.isIOS ? 'ios' : 'android',
      });
    } catch (error) {
      debugPrint('FCM token sync failed: $error');
    }
  }
}
