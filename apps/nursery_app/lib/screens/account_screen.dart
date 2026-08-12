import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/app_dialog.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class AccountScreen extends StatelessWidget {
  const AccountScreen({super.key});

  String _initials(User user) {
    final parts = user.name
        .trim()
        .split(RegExp(r'\s+'))
        .where((p) => p.isNotEmpty)
        .toList();
    if (parts.isEmpty) return 'G';
    if (parts.length == 1) return parts.first[0].toUpperCase();
    return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    if (!auth.bootstrapped) {
      return const Scaffold(body: AccountSkeleton());
    }

    if (auth.user == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Account')),
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(AppSpace.screen),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Spacer(),
                Center(
                  child: Container(
                    width: 72,
                    height: 72,
                    decoration: const BoxDecoration(
                      color: AppColors.primarySoft,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.person_outline_rounded,
                      size: 36,
                      color: AppColors.primaryDeep,
                    ),
                  ),
                ),
                const SizedBox(height: AppSpace.xl),
                Text(
                  'Welcome to GreenLeaf',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: AppSpace.sm),
                Text(
                  'Sign in to manage orders, wishlist and addresses.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: AppSpace.xxl),
                AppButton(
                  label: 'Sign in',
                  expanded: true,
                  onPressed: () =>
                      AuthNavigation.pushLogin(context, redirect: '/account'),
                ),
                const SizedBox(height: AppSpace.md),
                AppButton(
                  label: 'Create account',
                  variant: AppButtonVariant.secondary,
                  expanded: true,
                  onPressed: () => context.push(
                    AuthNavigation.registerLocation(redirect: '/account'),
                  ),
                ),
                const SizedBox(height: AppSpace.sm),
                TextButton(
                  onPressed: () => context.go('/'),
                  child: const Text('Continue shopping'),
                ),
                const Spacer(flex: 2),
              ],
            ),
          ),
        ),
      );
    }

    final user = auth.user!;
    final first = user.name.split(' ').first;

    return Scaffold(
      appBar: AppBar(title: const Text('Account')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpace.screen),
        children: [
          AppSurfaceCard(
            padding: const EdgeInsets.all(AppSpace.lg),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 30,
                  backgroundColor: AppColors.primaryDeep,
                  child: Text(
                    _initials(user),
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 20,
                    ),
                  ),
                ),
                const SizedBox(width: AppSpace.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Hello, $first',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      Text(
                        user.email,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                      if (user.phone != null && user.phone!.isNotEmpty)
                        Text(
                          user.phone!,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpace.xl),
          _sectionLabel(context, 'Shopping'),
          _tile(
            context,
            title: 'My orders',
            icon: Icons.receipt_long_outlined,
            onTap: () => context.go('/orders'),
          ),
          _tile(
            context,
            title: 'Addresses',
            icon: Icons.location_on_outlined,
            onTap: () => context.push('/account/addresses'),
          ),
          _tile(
            context,
            title: 'Wishlist',
            icon: Icons.favorite_border_rounded,
            onTap: () => context.push('/wishlist'),
          ),
          _tile(
            context,
            title: 'Rewards',
            icon: Icons.stars_outlined,
            onTap: () => context.push('/account/rewards'),
          ),
          _tile(
            context,
            title: 'Subscriptions',
            icon: Icons.autorenew_rounded,
            onTap: () => context.push('/account/subscriptions'),
          ),
          _tile(
            context,
            title: 'Offers',
            icon: Icons.local_offer_outlined,
            onTap: () => context.push('/offers'),
          ),
          _tile(
            context,
            title: 'Find your plant',
            icon: Icons.spa_outlined,
            onTap: () => context.push('/find-your-plant'),
          ),
          const SizedBox(height: AppSpace.lg),
          _sectionLabel(context, 'Account'),
          _tile(
            context,
            title: 'Personal information',
            icon: Icons.badge_outlined,
            onTap: () => context.push('/account/profile'),
          ),
          _tile(
            context,
            title: 'Security',
            icon: Icons.lock_outline_rounded,
            onTap: () => AppFeedback.info(
              context,
              'Use Forgot password on the sign-in screen to reset your password.',
            ),
          ),
          _tile(
            context,
            title: 'Notifications',
            icon: Icons.notifications_none_rounded,
            onTap: () => context.push('/notifications'),
          ),
          _tile(
            context,
            title: 'Preferences',
            icon: Icons.tune_rounded,
            onTap: () => context.push('/account/preferences'),
          ),
          const SizedBox(height: AppSpace.lg),
          _sectionLabel(context, 'Support'),
          _tile(
            context,
            title: 'Help & support',
            icon: Icons.help_outline_rounded,
            onTap: () => AppFeedback.info(
              context,
              'Email support@greenleaf.example for help with your order.',
            ),
          ),
          _tile(
            context,
            title: 'Privacy policy',
            icon: Icons.privacy_tip_outlined,
            onTap: () => AppFeedback.info(
              context,
              'Privacy policy content will be linked here.',
            ),
          ),
          _tile(
            context,
            title: 'Terms & conditions',
            icon: Icons.description_outlined,
            onTap: () => AppFeedback.info(
              context,
              'Terms & conditions will be linked here.',
            ),
          ),
          const SizedBox(height: AppSpace.xxl),
          AppButton(
            label: 'Sign out',
            variant: AppButtonVariant.secondary,
            expanded: true,
            onPressed: () async {
              final confirmed = await AppDialog.confirm(
                context,
                title: 'Sign out?',
                message: 'You can sign back in anytime.',
                confirmLabel: 'Sign out',
                destructive: true,
              );
              if (!confirmed || !context.mounted) return;
              final auth = context.read<AuthProvider>();
              final cart = context.read<CartProvider>();
              final wishlist = context.read<WishlistProvider>();
              await auth.logout();
              wishlist.clearLocal();
              await cart.fetch();
              if (!context.mounted) return;
              context.go('/account');
            },
          ),
          const SizedBox(height: AppSpace.lg),
        ],
      ),
    );
  }

  Widget _sectionLabel(BuildContext context, String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpace.sm),
      child: Text(
        label.toUpperCase(),
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: AppColors.muted,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.6,
        ),
      ),
    );
  }

  Widget _tile(
    BuildContext context, {
    required String title,
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpace.sm),
      child: AppSurfaceCard(
        onTap: onTap,
        padding: EdgeInsets.zero,
        child: ListTile(
          leading: Icon(icon, color: AppColors.primaryDeep),
          title: Text(
            title,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
          trailing: const Icon(Icons.chevron_right_rounded),
        ),
      ),
    );
  }
}
