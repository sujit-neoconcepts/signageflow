import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/app_config.dart';

String labelFor(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .map(
      (part) =>
          part.isEmpty ? part : '${part[0].toUpperCase()}${part.substring(1)}',
    )
    .join(' ');

DateTime? parseDMY(String? value) {
  if (value == null || value.trim().isEmpty) return null;
  try {
    final parts = value.trim().split(' ');
    final dateParts = parts[0].split('-');
    if (dateParts.length < 3) return null;
    final day = int.parse(dateParts[0]);
    final month = int.parse(dateParts[1]);
    final year = int.parse(dateParts[2]);

    int hour = 0;
    int minute = 0;
    if (parts.length > 1) {
      final timeParts = parts[1].split(':');
      if (timeParts.length >= 2) {
        hour = int.parse(timeParts[0]);
        minute = int.parse(timeParts[1]);
      }
    }
    return DateTime(year, month, day, hour, minute);
  } catch (_) {
    return null;
  }
}

Color statusColorFor(dynamic value) {
  switch (value) {
    case 'pending':
      return Colors.amber.shade800;
    case 'accepted':
      return Colors.blue;
    case 'in_progress':
      return Colors.purple;
    case 'completed':
      return Colors.green;
    case 'verified':
      return Colors.teal;
    case 'closed':
      return Colors.blueGrey;
    default:
      return Colors.blueGrey;
  }
}

Color priorityColorFor(dynamic value) {
  switch (value) {
    case 'urgent':
      return Colors.red;
    case 'high':
      return Colors.deepOrange;
    case 'medium':
      return appAccent;
    default:
      return Colors.blueGrey;
  }
}

String bytesLabel(int bytes) {
  if (bytes <= 0) return '0 B';
  const sizes = ['B', 'KB', 'MB', 'GB'];
  var value = bytes.toDouble();
  var index = 0;
  while (value >= 1024 && index < sizes.length - 1) {
    value /= 1024;
    index++;
  }
  return '${value.toStringAsFixed(1)} ${sizes[index]}';
}

String moneyLabel(dynamic value) {
  final amount = switch (value) {
    num n => n.toDouble(),
    String s => double.tryParse(s.replaceAll(',', '')) ?? 0,
    _ => 0.0,
  };

  return NumberFormat.currency(locale: 'en_IN', symbol: '₹').format(amount);
}
