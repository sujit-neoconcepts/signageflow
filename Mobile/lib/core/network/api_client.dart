import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../app_config.dart';

class ApiClient {
  ApiClient(this.baseUrl, this.token);

  final String baseUrl;
  final String token;

  Uri uri(String path, [Map<String, String?> query = const {}]) {
    final cleaned = baseUrl.endsWith('/')
        ? baseUrl.substring(0, baseUrl.length - 1)
        : baseUrl;
    final queryParameters = Map<String, String?>.from(query)
      ..removeWhere((_, value) => value == null || value.isEmpty);

    return Uri.parse(
      '$cleaned/api/mobile$path',
    ).replace(queryParameters: queryParameters);
  }

  Map<String, String> get headers => {
    'Accept': 'application/json',
    'Authorization': 'Bearer $token',
  };

  Future<Map<String, dynamic>> getJson(
    String path, [
    Map<String, String?> query = const {},
  ]) async {
    try {
      final response = await http.get(uri(path, query), headers: headers);
      return _decode(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<Map<String, dynamic>> postJson(
    String path,
    Map<String, dynamic> body,
  ) async {
    try {
      final response = await http.post(
        uri(path),
        headers: {...headers, 'Content-Type': 'application/json'},
        body: jsonEncode(body),
      );
      return _decode(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<Map<String, dynamic>> multipart(
    String path,
    Map<String, String> fields,
    List<File> files,
  ) async {
    try {
      final request = http.MultipartRequest('POST', uri(path))
        ..headers.addAll(headers)
        ..fields.addAll(fields);
      for (final file in files) {
        request.files.add(
          await http.MultipartFile.fromPath('files[]', file.path),
        );
      }
      final response = await http.Response.fromStream(await request.send());
      return _decode(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> downloadAndOpen(String url, String fileName) async {
    try {
      final response = await http.get(Uri.parse(url), headers: headers);
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw Exception('Download failed');
      }
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/$fileName');
      await file.writeAsBytes(response.bodyBytes);
      await OpenFilex.open(file.path);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> logout() async {
    try {
      await http.post(uri('/logout'), headers: headers);
    } catch (e) {
      // Ignore network errors on logout, still delete the token locally.
    } finally {
      await appStorage.delete(key: 'token');
    }
  }

  Map<String, dynamic> _decode(http.Response response) {
    if (response.body.isEmpty) {
      return <String, dynamic>{};
    }

    Map<String, dynamic> json;
    try {
      json = jsonDecode(response.body) as Map<String, dynamic>;
    } on FormatException {
      if (response.body.contains('is offline')) {
        throw Exception('The Server is offline. Please try again later.');
      }
      throw Exception(
        'Invalid response from server. The server might be offline.',
      );
    }

    if (response.statusCode >= 200 && response.statusCode < 300) return json;
    final message =
        json['message'] ??
        (json['errors'] as Map?)?.values.firstOrNull?.first ??
        'Request failed';
    throw Exception(message);
  }

  Object _handleError(Object error) {
    if (error is SocketException) {
      return Exception(
        'Cannot connect to server. Please check your internet connection or if the API is running.',
      );
    }
    if (error is http.ClientException) {
      return Exception(
        'Connection error. Please check if the API server is online.',
      );
    }
    if (error is FormatException) {
      return Exception(
        'Invalid response from server. The server might be offline.',
      );
    }
    return error;
  }
}
