import 'package:nursery_app/core/back_navigation.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/resilient_image.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class _Step {
  const _Step({
    required this.keyName,
    required this.title,
    required this.options,
    this.multi = false,
  });

  final String keyName;
  final String title;
  final bool multi;
  final List<({String value, String label})> options;
}

const _steps = <_Step>[
  _Step(
    keyName: 'location',
    title: 'Where will you keep your plant?',
    options: [
      (value: 'indoor', label: 'Indoor'),
      (value: 'balcony', label: 'Balcony'),
      (value: 'terrace', label: 'Terrace'),
      (value: 'garden', label: 'Garden'),
      (value: 'office', label: 'Office'),
    ],
  ),
  _Step(
    keyName: 'sunlight',
    title: 'How much sunlight?',
    options: [
      (value: 'low', label: 'Low'),
      (value: 'bright_indirect', label: 'Bright indirect'),
      (value: 'partial', label: 'Partial'),
      (value: 'full_sun', label: 'Direct / full sun'),
    ],
  ),
  _Step(
    keyName: 'watering',
    title: 'How often can you water?',
    options: [
      (value: 'rarely', label: 'Rarely'),
      (value: 'weekly', label: 'Once a week'),
      (value: 'often', label: 'Several times a week'),
    ],
  ),
  _Step(
    keyName: 'experience',
    title: 'Your experience?',
    options: [
      (value: 'beginner', label: 'Beginner'),
      (value: 'intermediate', label: 'Intermediate'),
      (value: 'experienced', label: 'Experienced'),
    ],
  ),
  _Step(
    keyName: 'purpose',
    title: 'What matters most?',
    multi: true,
    options: [
      (value: 'low_maintenance', label: 'Low maintenance'),
      (value: 'air_purifying', label: 'Air purifying'),
      (value: 'flowers', label: 'Flowers'),
      (value: 'decoration', label: 'Decoration'),
      (value: 'pet_friendly', label: 'Pet-friendly'),
      (value: 'edible', label: 'Edible / herbs'),
    ],
  ),
];

class FindYourPlantScreen extends StatefulWidget {
  const FindYourPlantScreen({super.key});

  @override
  State<FindYourPlantScreen> createState() => _FindYourPlantScreenState();
}

class _FindYourPlantScreenState extends State<FindYourPlantScreen> {
  var _started = false;
  var _step = 0;
  var _busy = false;
  final _answers = <String, dynamic>{
    'location': '',
    'sunlight': '',
    'watering': '',
    'experience': '',
    'purpose': <String>[],
  };
  List<_MatchRow>? _results;
  var _approximate = false;
  String? _error;

  bool get _canContinue {
    final s = _steps[_step];
    if (s.multi) return true;
    return (_answers[s.keyName] as String?)?.isNotEmpty == true;
  }

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final data = await context.read<ApiClient>().sendData(
        'POST',
        '/plant-finder/match',
        body: {
          'location': _answers['location'],
          'sunlight': _answers['sunlight'],
          'watering': _answers['watering'],
          'experience': _answers['experience'],
          'purpose': _answers['purpose'],
        },
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      final rows = ((data['results'] as List?) ?? [])
          .whereType<Map>()
          .map((e) {
            final m = Map<String, dynamic>.from(e);
            final product = ProductSummary.fromJson(
              Map<String, dynamic>.from(m['product'] as Map),
            );
            final reasons = ((m['match_reasons'] as List?) ?? [])
                .map((r) => r.toString())
                .toList();
            return _MatchRow(
              product: product,
              score: (m['match_score'] as num?)?.toInt() ?? 0,
              reasons: reasons,
            );
          })
          .toList();
      if (!mounted) return;
      setState(() {
        _results = rows;
        _approximate = data['approximate'] == true;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e is ApiException ? e.message : 'Could not find matches';
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _next() async {
    if (_step < _steps.length - 1) {
      setState(() => _step += 1);
      return;
    }
    await _submit();
  }

  void _reset() {
    setState(() {
      _started = false;
      _step = 0;
      _results = null;
      _approximate = false;
      _error = null;
      _answers
        ..['location'] = ''
        ..['sunlight'] = ''
        ..['watering'] = ''
        ..['experience'] = ''
        ..['purpose'] = <String>[];
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Find your plant'),
        leading: const GreenLeafBackButton(),
      ),
      body: !_started
          ? _Start(onStart: () => setState(() => _started = true))
          : _results != null
              ? _Results(
                  results: _results!,
                  approximate: _approximate,
                  onAgain: _reset,
                  onCatalog: () => context.push('/catalog?product_type=plant'),
                )
              : _Question(
                  stepIndex: _step,
                  total: _steps.length,
                  step: _steps[_step],
                  answers: _answers,
                  busy: _busy,
                  error: _error,
                  canContinue: _canContinue,
                  onBack: _step == 0
                      ? null
                      : () => setState(() => _step -= 1),
                  onNext: _busy || !_canContinue ? null : () => _next(),
                  onSelect: (value) {
                    final s = _steps[_step];
                    setState(() {
                      if (s.multi) {
                        final list =
                            List<String>.from(_answers['purpose'] as List);
                        if (list.contains(value)) {
                          list.remove(value);
                        } else {
                          list.add(value);
                        }
                        _answers['purpose'] = list;
                      } else {
                        _answers[s.keyName] = value;
                      }
                    });
                  },
                ),
    );
  }
}

class _MatchRow {
  _MatchRow({
    required this.product,
    required this.score,
    required this.reasons,
  });

  final ProductSummary product;
  final int score;
  final List<String> reasons;
}

class _Start extends StatelessWidget {
  const _Start({required this.onStart});

  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.local_florist_outlined, size: 56, color: AppColors.primary),
          const SizedBox(height: 16),
          Text(
            'Find the perfect plant',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          const SizedBox(height: 8),
          Text(
            'Answer a few questions. We match real plant attributes — not fake AI.',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 24),
          FilledButton(onPressed: onStart, child: const Text('Start')),
        ],
      ),
    );
  }
}

class _Question extends StatelessWidget {
  const _Question({
    required this.stepIndex,
    required this.total,
    required this.step,
    required this.answers,
    required this.busy,
    required this.error,
    required this.canContinue,
    required this.onSelect,
    this.onBack,
    this.onNext,
  });

  final int stepIndex;
  final int total;
  final _Step step;
  final Map<String, dynamic> answers;
  final bool busy;
  final String? error;
  final bool canContinue;
  final ValueChanged<String> onSelect;
  final VoidCallback? onBack;
  final VoidCallback? onNext;

  @override
  Widget build(BuildContext context) {
    final selected = step.multi
        ? List<String>.from(answers['purpose'] as List)
        : <String>[answers[step.keyName]?.toString() ?? ''];

    return Column(
      children: [
        LinearProgressIndicator(value: (stepIndex + 1) / total),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Text(
                'Step ${stepIndex + 1} of $total',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 8),
              Text(step.title, style: Theme.of(context).textTheme.headlineSmall),
              if (step.multi)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    'Select all that apply',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              const SizedBox(height: 16),
              ...step.options.map((o) {
                final active = selected.contains(o.value);
                return Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Semantics(
                    button: true,
                    selected: active,
                    label: active
                        ? '${o.label}, selected'
                        : o.label,
                    child: InkWell(
                      onTap: () => onSelect(o.value),
                      borderRadius: BorderRadius.circular(AppRadii.md),
                      child: Container(
                        width: double.infinity,
                        constraints: const BoxConstraints(minHeight: 48),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(AppRadii.md),
                          border: Border.all(
                            color: active
                                ? AppColors.primary
                                : AppColors.border,
                            width: active ? 2 : 1,
                          ),
                          color: active
                              ? AppColors.secondarySoft
                              : Colors.white,
                        ),
                        child: Row(
                          children: [
                            Icon(
                              active
                                  ? Icons.check_circle
                                  : Icons.circle_outlined,
                              color: active
                                  ? AppColors.primary
                                  : AppColors.muted,
                              size: 22,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                o.label,
                                style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                  color: active
                                      ? AppColors.primaryDeep
                                      : AppColors.ink,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                );
              }),
              if (error != null)
                Text(error!, style: const TextStyle(color: AppColors.error)),
            ],
          ),
        ),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: onBack,
                    child: const Text('Back'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    onPressed: onNext,
                    child: Text(
                      busy
                          ? 'Finding…'
                          : stepIndex == total - 1
                              ? 'See matches'
                              : 'Continue',
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _Results extends StatelessWidget {
  const _Results({
    required this.results,
    required this.approximate,
    required this.onAgain,
    required this.onCatalog,
  });

  final List<_MatchRow> results;
  final bool approximate;
  final VoidCallback onAgain;
  final VoidCallback onCatalog;

  @override
  Widget build(BuildContext context) {
    final best = results.isNotEmpty ? results.first : null;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
      children: [
        Text(
          results.isEmpty
              ? "We couldn't find an exact match"
              : approximate
                  ? 'Closest matches'
                  : 'We found ${results.length} plants for you',
          style: Theme.of(context).textTheme.headlineMedium,
        ),
        const SizedBox(height: 6),
        Text(
          approximate
              ? 'Approximate matches based on your preferences.'
              : 'Ranked by how well each plant fits your answers.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        if (best != null) ...[
          const SizedBox(height: 16),
          AppSurfaceCard(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AppBadge(
                  label: 'Best match · ${best.score}%',
                  tone: AppBadgeTone.success,
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(AppRadii.md),
                      child: ResilientNetworkImage(
                            url: best.product.thumbnailUrl,
                            width: 88,
                            height: 88,
                            fit: BoxFit.cover,
                          ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            best.product.name,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                          Text(money(best.product.price)),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                ...best.reasons.map((r) => Text('✓ $r')),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () =>
                            context.push('/product/${best.product.slug}'),
                        child: const Text('View plant'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: FilledButton(
                        onPressed: best.product.stockStatus == 'out_of_stock'
                            ? null
                            : () async {
                                try {
                                  await context
                                      .read<CartProvider>()
                                      .addItem(best.product.id);
                                  if (context.mounted) {
                                    AppFeedback.success(
                                      context,
                                      'Added to cart',
                                    );
                                  }
                                } catch (e) {
                                  if (context.mounted) {
                                    AppFeedback.error(
                                      context,
                                      e is ApiException
                                          ? e.message
                                          : 'Could not add',
                                    );
                                  }
                                }
                              },
                        child: Text(
                          best.product.stockStatus == 'out_of_stock'
                              ? 'Out of stock'
                              : 'Add to cart',
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
        if (results.length > 1) ...[
          const SizedBox(height: 20),
          Text(
            'Other great matches',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 8),
          ...results.skip(1).map(
            (row) => ListTile(
              contentPadding: EdgeInsets.zero,
              leading: ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: ResilientNetworkImage(
                        url: row.product.thumbnailUrl,
                        width: 48,
                        height: 48,
                        fit: BoxFit.cover,
                      ),
                    ),
              title: Text(row.product.name),
              subtitle: Text('${row.score}% match · ${money(row.product.price)}'),
              onTap: () => context.push('/product/${row.product.slug}'),
            ),
          ),
        ],
        const SizedBox(height: 20),
        FilledButton.tonal(onPressed: onAgain, child: const Text('Start again')),
        const SizedBox(height: 8),
        OutlinedButton(
          onPressed: onCatalog,
          child: const Text('Browse all plants'),
        ),
      ],
    );
  }
}
