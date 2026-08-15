import 'package:flutter/material.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

/// Reviews list + submit form for PDP (same API as website).
class ProductReviewsSection extends StatefulWidget {
  const ProductReviewsSection({
    super.key,
    required this.productId,
    required this.productSlug,
    this.openForm = false,
  });

  final int productId;
  final String productSlug;
  final bool openForm;

  @override
  State<ProductReviewsSection> createState() => _ProductReviewsSectionState();
}

class _ProductReviewsSectionState extends State<ProductReviewsSection> {
  List<ProductReview> _reviews = const [];
  double? _avg;
  int? _count;
  bool _loading = true;
  String? _error;
  bool _showForm = false;
  bool _submitting = false;
  int _rating = 5;
  bool? _eligible;
  String? _eligibilityReason;
  final _title = TextEditingController();
  final _body = TextEditingController();

  @override
  void initState() {
    super.initState();
    _showForm = widget.openForm;
    _load();
  }

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final soft = _reviews.isNotEmpty;
    if (mounted) {
      setState(() {
        if (!soft) _loading = true;
        _error = null;
      });
    }
    try {
      final api = context.read<ApiClient>();
      final auth = context.read<AuthProvider>();
      final result = await api.getDataWithMeta(
        '/products/${widget.productId}/reviews',
        query: {'per_page': 12, 'sort': 'newest'},
        map: (data) => (data as List)
            .whereType<Map>()
            .map((e) => ProductReview.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
      final summary = result.meta?['summary'];
      bool? eligible;
      String? reason;
      if (auth.user != null) {
        try {
          final el = await api.getData(
            '/products/${widget.productId}/review-eligibility',
            map: (d) => Map<String, dynamic>.from(d as Map),
          );
          eligible = el['eligible'] == true;
          reason = el['reason']?.toString();
        } catch (_) {}
      }
      if (!mounted) return;
      setState(() {
        _reviews = result.data;
        if (summary is Map) {
          _avg = (summary['rating_avg'] as num?)?.toDouble();
          _count = (summary['rating_count'] as num?)?.toInt();
        }
        _eligible = eligible;
        _eligibilityReason = reason;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        if (_reviews.isEmpty) _error = e.toString();
      });
    }
  }

  Future<void> _submit() async {
    final auth = context.read<AuthProvider>();
    if (auth.user == null) {
      AuthNavigation.pushLogin(
        context,
        redirect: '/product/${widget.productSlug}',
      );
      return;
    }
    setState(() => _submitting = true);
    try {
      await context.read<ApiClient>().sendData(
        'POST',
        '/products/${widget.productId}/reviews',
        body: {
          'rating': _rating,
          if (_title.text.trim().isNotEmpty) 'title': _title.text.trim(),
          if (_body.text.trim().isNotEmpty) 'body': _body.text.trim(),
        },
        map: (data) => data,
      );
      if (!mounted) return;
      AppFeedback.success(
        context,
        'Thanks! Your review is pending moderation.',
      );
      setState(() {
        _showForm = false;
        _rating = 5;
        _title.clear();
        _body.clear();
        _eligible = false;
        _eligibilityReason = 'You have already reviewed this product';
      });
      await _load();
    } catch (e) {
      if (!mounted) return;
      AppFeedback.error(context, ErrorStateView.sanitize(e.toString()));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final signedIn = context.watch<AuthProvider>().user != null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Customer reviews',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
            ),
            TextButton(
              onPressed: () {
                if (!signedIn) {
                  AuthNavigation.pushLogin(
                    context,
                    redirect: '/product/${widget.productSlug}',
                  );
                  return;
                }
                if (_eligible == false) {
                  AppFeedback.info(
                    context,
                    _eligibilityReason ?? 'Not eligible to review',
                  );
                  return;
                }
                setState(() => _showForm = !_showForm);
              },
              child: Text(
                _showForm
                    ? 'Cancel'
                    : (_eligible == false ? 'Not eligible' : 'Write review'),
              ),
            ),
          ],
        ),
        if (_avg != null || _count != null)
          Padding(
            padding: const EdgeInsets.only(top: AppSpace.xs),
            child: RatingRow(avg: _avg, count: _count),
          ),
        if (_showForm && signedIn && _eligible != false) ...[
          const SizedBox(height: AppSpace.md),
          Text(
            'Reviews appear after moderation. Purchase required.',
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: AppColors.inkSoft),
          ),
          const SizedBox(height: AppSpace.sm),
          Wrap(
            spacing: AppSpace.sm,
            children: [5, 4, 3, 2, 1]
                .map(
                  (n) => ChoiceChip(
                    label: Text('$n★'),
                    selected: _rating == n,
                    onSelected: (_) => setState(() => _rating = n),
                  ),
                )
                .toList(),
          ),
          const SizedBox(height: AppSpace.sm),
          TextField(
            controller: _title,
            decoration: const InputDecoration(labelText: 'Title (optional)'),
            textCapitalization: TextCapitalization.sentences,
          ),
          const SizedBox(height: AppSpace.sm),
          TextField(
            controller: _body,
            decoration: const InputDecoration(
              labelText: 'Your experience (optional)',
            ),
            maxLines: 4,
            textCapitalization: TextCapitalization.sentences,
          ),
          const SizedBox(height: AppSpace.md),
          FilledButton(
            onPressed: _submitting ? null : _submit,
            child: Text(_submitting ? 'Submitting…' : 'Submit review'),
          ),
        ],
        const SizedBox(height: AppSpace.lg),
        if (_loading && _reviews.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: AppSpace.lg),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_error != null)
          Text(
            ErrorStateView.sanitize(_error),
            style: Theme.of(context).textTheme.bodySmall,
          )
        else if (_reviews.isEmpty)
          Text(
            'No reviews yet.',
            style: Theme.of(
              context,
            ).textTheme.bodyMedium?.copyWith(color: AppColors.inkSoft),
          )
        else
          ..._reviews.map(
            (r) => Padding(
              padding: const EdgeInsets.only(bottom: AppSpace.md),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(AppSpace.md),
                decoration: BoxDecoration(
                  border: Border.all(color: AppColors.border),
                  borderRadius: BorderRadius.circular(AppRadii.lg),
                  color: AppColors.surface,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            r.userName,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ),
                        RatingRow(avg: r.rating.toDouble()),
                      ],
                    ),
                    if (r.verifiedPurchase) ...[
                      const SizedBox(height: AppSpace.xs),
                      Text(
                        'Verified purchase',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.primaryDeep,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                    if (r.title != null && r.title!.isNotEmpty) ...[
                      const SizedBox(height: AppSpace.xs),
                      Text(
                        r.title!,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ],
                    if (r.body != null && r.body!.isNotEmpty) ...[
                      const SizedBox(height: AppSpace.xs),
                      Text(r.body!),
                    ],
                    if (r.createdAt != null && r.createdAt!.isNotEmpty) ...[
                      const SizedBox(height: AppSpace.xs),
                      Text(
                        formatOrderDate(r.createdAt),
                        style: Theme.of(
                          context,
                        ).textTheme.bodySmall?.copyWith(color: AppColors.muted),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}
