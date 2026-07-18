import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

const appStorage = FlutterSecureStorage();
const appAccent = Color(0xFF0EA5E9);
const appInk = Color(0xFF0F172A);

String get apiBaseUrl =>
    (dotenv.env['API_BASE_URL'] ?? '').trim().replaceAll(RegExp(r'/+$'), '');
