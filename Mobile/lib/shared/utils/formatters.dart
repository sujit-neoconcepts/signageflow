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
