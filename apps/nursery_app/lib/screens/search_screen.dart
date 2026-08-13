import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_search_field.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

/// Dedicated mobile search experience with suggestions + recent terms.
class SearchScreen extends StatefulWidget {
  const SearchScreen({super.key, this.initialQuery});

  final String? initialQuery;

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  static const _recentKey = 'recent_searches_v1';
  static const _storage = FlutterSecureStorage();

  late final TextEditingController _controller;
  final _focus = FocusNode();
  Timer? _debounce;
  int _suggestionReq = 0;

  List<_Suggestion> _suggestions = [];
  List<String> _recent = [];
  bool _loadingSuggestions = false;
  String? _error;

  static const _popular = [
    'money plant',
    'snake plant',
    'indoor',
    'aloe',
    'beginner',
  ];

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialQuery ?? '');
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _focus.requestFocus();
      _loadRecent();
      final q = _controller.text.trim();
      if (q.length >= 2) _fetchSuggestions(q);
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  Future<void> _loadRecent() async {
    try {
      final raw = await _storage.read(key: _recentKey);
      if (raw == null || raw.isEmpty) return;
      final parts = raw
          .split('\n')
          .map((e) => e.trim())
          .where((e) => e.isNotEmpty)
          .take(8)
          .toList();
      if (mounted) setState(() => _recent = parts);
    } catch (_) {}
  }

  Future<void> _pushRecent(String term) async {
    final next = [term, ..._recent.where((e) => e != term)].take(8).toList();
    setState(() => _recent = next);
    try {
      await _storage.write(key: _recentKey, value: next.join('\n'));
    } catch (_) {}
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    final q = value.trim();
    if (q.length < 2) {
      setState(() {
        _suggestions = [];
        _loadingSuggestions = false;
        _error = null;
      });
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 280), () {
      _fetchSuggestions(q);
    });
  }

  Future<void> _fetchSuggestions(String q) async {
    final req = ++_suggestionReq;
    setState(() {
      _loadingSuggestions = true;
      _error = null;
    });
    try {
      final resolved =
          await context.read<CatalogRepository>().searchSuggestions(q);
      final rows = resolved.data
          .map(
            (term) => _Suggestion(label: term, type: 'query', slug: ''),
          )
          .toList();
      if (!mounted || req != _suggestionReq) return;
      setState(() {
        _suggestions = rows;
        _loadingSuggestions = false;
        _error = null;
      });
    } catch (e) {
      if (!mounted || req != _suggestionReq) return;
      setState(() {
        _loadingSuggestions = false;
        _error = null;
        _suggestions = [];
      });
    }
  }

  void _submit([String? term]) {
    final q = (term ?? _controller.text).trim();
    if (q.isEmpty) return;
    _pushRecent(q);
    context.go('/catalog?q=${Uri.encodeQueryComponent(q)}');
  }

  void _openSuggestion(_Suggestion s) {
    if (s.type == 'category') {
      context.go('/catalog?category=${Uri.encodeQueryComponent(s.slug)}');
      return;
    }
    context.push('/product/${s.slug}');
  }

  @override
  Widget build(BuildContext context) {
    final q = _controller.text.trim();
    final showSuggestions = q.length >= 2;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Search'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/');
            }
          },
        ),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpace.screen,
              AppSpace.sm,
              AppSpace.screen,
              AppSpace.sm,
            ),
            child: AppSearchField(
              controller: _controller,
              focusNode: _focus,
              onChanged: _onChanged,
              onSubmitted: (_) => _submit(),
              onClear: () {
                _controller.clear();
                setState(() {
                  _suggestions = [];
                  _loadingSuggestions = false;
                });
                _focus.requestFocus();
              },
            ),
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                AppSpace.screen,
                AppSpace.sm,
                AppSpace.screen,
                AppSpace.xxl,
              ),
              children: [
                if (showSuggestions) ...[
                  Row(
                    children: [
                      Text(
                        'Suggestions',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const Spacer(),
                      if (_loadingSuggestions)
                        const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                    ],
                  ),
                  const SizedBox(height: AppSpace.sm),
                  if (_error != null)
                    Text(
                      ErrorStateView.sanitize(_error),
                      style: Theme.of(context).textTheme.bodySmall,
                    )
                  else if (!_loadingSuggestions && _suggestions.isEmpty)
                    Text(
                      'No suggestions for “$q”. Tap search to see all results.',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: AppColors.inkSoft,
                      ),
                    )
                  else
                    ..._suggestions.map(
                      (s) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(
                          s.type == 'category'
                              ? Icons.grid_view_rounded
                              : Icons.spa_outlined,
                          color: AppColors.primaryDeep,
                        ),
                        title: Text(s.label),
                        subtitle: Text(
                          s.type == 'category' ? 'Category' : 'Product',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        onTap: () => _openSuggestion(s),
                      ),
                    ),
                  const SizedBox(height: AppSpace.lg),
                  FilledButton(
                    onPressed: () => _submit(),
                    child: Text('Search “$q”'),
                  ),
                ] else ...[
                  if (_recent.isNotEmpty) ...[
                    Text(
                      'Recent',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: AppSpace.sm),
                    Wrap(
                      spacing: AppSpace.sm,
                      runSpacing: AppSpace.sm,
                      children: _recent
                          .map(
                            (term) => ActionChip(
                              label: Text(term),
                              onPressed: () {
                                _controller.text = term;
                                _submit(term);
                              },
                            ),
                          )
                          .toList(),
                    ),
                    const SizedBox(height: AppSpace.xl),
                  ],
                  Text(
                    'Popular searches',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: AppSpace.sm),
                  Wrap(
                    spacing: AppSpace.sm,
                    runSpacing: AppSpace.sm,
                    children: _popular
                        .map(
                          (term) => ActionChip(
                            label: Text(term),
                            onPressed: () {
                              _controller.text = term;
                              _submit(term);
                            },
                          ),
                        )
                        .toList(),
                  ),
                  const SizedBox(height: AppSpace.xl),
                  TextButton(
                    onPressed: () => context.go('/categories'),
                    child: const Text('Browse categories'),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Suggestion {
  _Suggestion({required this.type, required this.label, required this.slug});

  final String type;
  final String label;
  final String slug;

  factory _Suggestion.fromJson(Map<String, dynamic> json) => _Suggestion(
    type: json['type']?.toString() ?? 'product',
    label: json['label']?.toString() ?? '',
    slug: json['slug']?.toString() ?? '',
  );
}
