import 'package:flutter/material.dart';
import 'package:nursery_app/theme/tokens.dart';

/// Rounded botanical search field for headers and catalog.
class AppSearchField extends StatelessWidget {
  const AppSearchField({
    super.key,
    this.controller,
    this.focusNode,
    this.hintText = 'Search plants, pots, seeds…',
    this.readOnly = false,
    this.onTap,
    this.onChanged,
    this.onSubmitted,
    this.onClear,
    this.autofocus = false,
  });

  final TextEditingController? controller;
  final FocusNode? focusNode;
  final String hintText;
  final bool readOnly;
  final VoidCallback? onTap;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final VoidCallback? onClear;
  final bool autofocus;

  @override
  Widget build(BuildContext context) {
    Widget field(bool hasText) {
      return Semantics(
        textField: !readOnly,
        button: readOnly,
        label: hintText,
        child: Material(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(AppRadii.full),
          child: InkWell(
            onTap: readOnly ? onTap : null,
            borderRadius: BorderRadius.circular(AppRadii.full),
            child: Ink(
              height: AppTouch.iconButton,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(AppRadii.full),
                border: Border.all(color: AppColors.border),
              ),
              child: Row(
                children: [
                  const SizedBox(width: AppSpace.md),
                  const Icon(
                    Icons.search_rounded,
                    color: AppColors.muted,
                    size: 22,
                  ),
                  const SizedBox(width: AppSpace.sm),
                  Expanded(
                    child: readOnly
                        ? Text(
                            hintText,
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(color: AppColors.muted),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          )
                        : TextField(
                            controller: controller,
                            focusNode: focusNode,
                            autofocus: autofocus,
                            onChanged: onChanged,
                            onSubmitted: onSubmitted,
                            textInputAction: TextInputAction.search,
                            decoration: InputDecoration(
                              hintText: hintText,
                              border: InputBorder.none,
                              enabledBorder: InputBorder.none,
                              focusedBorder: InputBorder.none,
                              filled: false,
                              isDense: true,
                              contentPadding: EdgeInsets.zero,
                            ),
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(color: AppColors.ink),
                          ),
                  ),
                  if (!readOnly && hasText)
                    IconButton(
                      tooltip: 'Clear search',
                      onPressed: () {
                        controller?.clear();
                        onClear?.call();
                        onChanged?.call('');
                      },
                      icon: const Icon(Icons.close_rounded, size: 20),
                    )
                  else
                    const SizedBox(width: AppSpace.md),
                ],
              ),
            ),
          ),
        ),
      );
    }

    if (controller == null) return field(false);
    return ListenableBuilder(
      listenable: controller!,
      builder: (context, _) => field(controller!.text.isNotEmpty),
    );
  }
}
