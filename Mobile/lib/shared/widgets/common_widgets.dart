import 'package:flutter/material.dart';

import '../../core/app_config.dart';

class AppPill extends StatelessWidget {
  const AppPill({super.key, required this.text, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .11),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: .22)),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class AppAutocompleteField extends StatelessWidget {
  const AppAutocompleteField({
    super.key,
    required this.controller,
    required this.label,
    required this.options,
  });

  final TextEditingController controller;
  final String label;
  final List<String> options;

  @override
  Widget build(BuildContext context) {
    return Autocomplete<String>(
      optionsBuilder: (value) => options
          .where(
            (option) => option.toLowerCase().contains(value.text.toLowerCase()),
          )
          .take(20),
      onSelected: (value) => controller.text = value,
      fieldViewBuilder: (_, fieldController, focusNode, onSubmit) {
        fieldController.text = controller.text;
        fieldController.addListener(
          () => controller.text = fieldController.text,
        );
        return TextField(
          controller: fieldController,
          focusNode: focusNode,
          decoration: InputDecoration(labelText: label),
        );
      },
    );
  }
}

class AppPanel extends StatelessWidget {
  const AppPanel({
    super.key,
    required this.title,
    required this.child,
    this.trailing,
  });

  final String title;
  final Widget child;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                    color: appInk,
                  ),
                ),
              ),
              ?trailing,
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}
