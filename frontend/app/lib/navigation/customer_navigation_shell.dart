import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/clerk_auth_adapter.dart';
import '../core/presentation/app_brand_logo.dart';
import '../features/categories/data/category_repository.dart';
import '../theme/app_spacing.dart';
import 'app_routes.dart';
import 'drawer_category_controller.dart';

class CustomerNavigationShell extends StatefulWidget {
  const CustomerNavigationShell({
    super.key,
    required this.location,
    required this.child,
    required this.authState,
    required this.getAuthStatus,
    required this.categoryRepository,
  });

  final String location;
  final Widget child;
  final Listenable authState;
  final ClerkAuthStatus Function() getAuthStatus;
  final CategoryRepository? categoryRepository;

  @override
  State<CustomerNavigationShell> createState() =>
      _CustomerNavigationShellState();
}

class _CustomerNavigationShellState extends State<CustomerNavigationShell> {
  final GlobalKey<ScaffoldState> _scaffoldKey = GlobalKey<ScaffoldState>();
  late final DrawerCategoryController _categories = DrawerCategoryController(
    widget.categoryRepository,
  );

  @override
  void dispose() {
    _categories.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isTopLevel = _isTopLevel(widget.location);
    return Scaffold(
      key: _scaffoldKey,
      appBar: _CustomerAppBar(
        title: _titleFor(widget.location),
        isTopLevel: isTopLevel,
        onOpenDrawer: () {
          _categories.load();
          _scaffoldKey.currentState?.openDrawer();
        },
        onBack: () {
          if (context.canPop()) {
            context.pop();
          } else {
            context.go(AppRoutes.home);
          }
        },
        authState: widget.authState,
        getAuthStatus: widget.getAuthStatus,
      ),
      drawer: _CustomerDrawer(
        location: widget.location,
        categories: _categories,
      ),
      body: widget.child,
      onDrawerChanged: (isOpen) {
        if (isOpen) _categories.load();
      },
    );
  }

  bool _isTopLevel(String location) => <String>{
    AppRoutes.home,
    AppRoutes.products,
    AppRoutes.categories,
    AppRoutes.search,
    AppRoutes.furnitureRequests,
    AppRoutes.contact,
    AppRoutes.about,
    AppRoutes.privacyPolicy,
    AppRoutes.termsAndConditions,
    AppRoutes.account,
  }.contains(location);

  String _titleFor(String location) {
    if (location.startsWith('${AppRoutes.products}/')) return 'Furniture';
    if (location.startsWith('${AppRoutes.categories}/')) return 'Category';
    if (location.startsWith('${AppRoutes.furnitureRequests}/')) {
      return 'Furniture request';
    }
    return switch (location) {
      AppRoutes.products => 'Furniture',
      AppRoutes.categories => 'Categories',
      AppRoutes.search => 'Search',
      AppRoutes.furnitureRequests => 'Furniture request',
      AppRoutes.contact => 'Contact us',
      AppRoutes.about => 'About SL Furnitures',
      AppRoutes.privacyPolicy => 'Privacy Policy',
      AppRoutes.termsAndConditions => 'Terms & Conditions',
      AppRoutes.account => 'Account',
      _ => 'SL Furnitures',
    };
  }
}

class _CustomerAppBar extends StatelessWidget implements PreferredSizeWidget {
  const _CustomerAppBar({
    required this.title,
    required this.isTopLevel,
    required this.onOpenDrawer,
    required this.onBack,
    required this.authState,
    required this.getAuthStatus,
  });

  final String title;
  final bool isTopLevel;
  final VoidCallback onOpenDrawer;
  final VoidCallback onBack;
  final Listenable authState;
  final ClerkAuthStatus Function() getAuthStatus;

  @override
  Size get preferredSize => const Size.fromHeight(AppSpacing.space9);

  @override
  Widget build(BuildContext context) => AppBar(
    key: const ValueKey<String>('navigation.app_bar'),
    automaticallyImplyLeading: false,
    toolbarHeight: AppSpacing.space9,
    flexibleSpace: SafeArea(
      child: Stack(
        alignment: Alignment.center,
        children: <Widget>[
          Center(
            child: Semantics(
              button: true,
              label: 'SL Furnitures home',
              child: InkWell(
                onTap: () => context.go(AppRoutes.home),
                // The enclosing node already names this control, so the logo's
                // own label is excluded: otherwise assistive technology
                // announces the brand twice.
                child: const ExcludeSemantics(
                  child: AppBrandLogo(height: AppSpacing.space7),
                ),
              ),
            ),
          ),
          Align(
            alignment: Alignment.centerLeft,
            child: isTopLevel
                ? IconButton(
                    key: const ValueKey<String>('navigation.menu_button'),
                    tooltip: 'Open navigation menu',
                    onPressed: onOpenDrawer,
                    icon: const Icon(Icons.menu),
                  )
                : BackButton(
                    key: const ValueKey<String>('navigation.back_button'),
                    onPressed: onBack,
                  ),
          ),
          Align(
            alignment: Alignment.centerRight,
            child: ListenableBuilder(
              listenable: authState,
              builder: (context, _) =>
                  _AccountAction(status: getAuthStatus(), title: title),
            ),
          ),
        ],
      ),
    ),
  );
}

class _AccountAction extends StatelessWidget {
  const _AccountAction({required this.status, required this.title});

  final ClerkAuthStatus status;
  final String title;

  @override
  Widget build(BuildContext context) {
    final disabled =
        status == ClerkAuthStatus.initializing ||
        status == ClerkAuthStatus.temporarilyUnavailable;
    final tooltip = switch (status) {
      ClerkAuthStatus.initializing => 'Account is loading',
      ClerkAuthStatus.temporarilyUnavailable => 'Account is unavailable',
      ClerkAuthStatus.actionRequired => 'Continue sign in',
      ClerkAuthStatus.signedIn || ClerkAuthStatus.signedOut => 'Account',
    };
    return IconButton(
      key: const ValueKey<String>('navigation.account_button'),
      tooltip: tooltip,
      onPressed: disabled
          ? null
          : () {
              switch (status) {
                case ClerkAuthStatus.signedIn:
                  context.go(AppRoutes.account);
                case ClerkAuthStatus.signedOut:
                  _showGuestAccountSheet(context);
                case ClerkAuthStatus.actionRequired:
                  context.go(AppRoutes.signIn);
                case ClerkAuthStatus.initializing ||
                    ClerkAuthStatus.temporarilyUnavailable:
                  break;
              }
            },
      icon: const Icon(Icons.person_outline),
    );
  }
}

void _showGuestAccountSheet(BuildContext context) {
  showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    useSafeArea: true,
    builder: (sheetContext) => SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.space6,
          AppSpacing.space2,
          AppSpacing.space6,
          AppSpacing.space7,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Semantics(
                    header: true,
                    child: Text(
                      'Account',
                      style: Theme.of(sheetContext).textTheme.headlineSmall,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: 'Close account options',
                  onPressed: () => Navigator.of(sheetContext).pop(),
                  icon: const Icon(Icons.close),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.space4),
            Text(
              'Welcome to SL FURNITURES',
              style: Theme.of(sheetContext).textTheme.titleLarge,
            ),
            const SizedBox(height: AppSpacing.space2),
            const Text('Sign in to manage your account.'),
            const SizedBox(height: AppSpacing.space6),
            FilledButton(
              key: const ValueKey<String>('navigation.sign_in_button'),
              onPressed: () {
                Navigator.of(sheetContext).pop();
                context.go(AppRoutes.signIn);
              },
              child: const Text('Sign in'),
            ),
            const SizedBox(height: AppSpacing.space3),
            OutlinedButton(
              key: const ValueKey<String>('navigation.create_account_button'),
              onPressed: () {
                Navigator.of(sheetContext).pop();
                context.go(AppRoutes.signUp);
              },
              child: const Text('Create account'),
            ),
          ],
        ),
      ),
    ),
  );
}

class _CustomerDrawer extends StatelessWidget {
  const _CustomerDrawer({required this.location, required this.categories});

  final String location;
  final DrawerCategoryController categories;

  @override
  Widget build(BuildContext context) => Semantics(
    label: 'SL Furnitures navigation',
    child: NavigationDrawer(
      children: <Widget>[
        const Padding(
          padding: EdgeInsets.fromLTRB(
            AppSpacing.space6,
            AppSpacing.space5,
            AppSpacing.space6,
            AppSpacing.space3,
          ),
          child: AppBrandLogo(height: AppSpacing.space6),
        ),
        _DrawerSearch(onTap: () => _go(context, AppRoutes.search)),
        const _DrawerSection(label: 'Explore'),
        _Destination(
          icon: Icons.home_outlined,
          label: 'Home',
          selected: location == AppRoutes.home,
          onTap: () => _go(context, AppRoutes.home),
        ),
        _Destination(
          icon: Icons.chair_outlined,
          label: 'All furniture',
          selected: location == AppRoutes.products,
          onTap: () => _go(context, AppRoutes.products),
        ),
        const _DrawerSection(label: 'Categories'),
        ListenableBuilder(
          listenable: categories,
          builder: (context, _) =>
              _CategoryDestinations(controller: categories, location: location),
        ),
        const _DrawerSection(label: 'Services'),
        _Destination(
          icon: Icons.design_services_outlined,
          label: 'Request furniture',
          selected: location == AppRoutes.furnitureRequests,
          onTap: () => _go(context, AppRoutes.furnitureRequests),
        ),
        _Destination(
          icon: Icons.mail_outline,
          label: 'Contact us',
          selected: location == AppRoutes.contact,
          onTap: () => _go(context, AppRoutes.contact),
        ),
        const _DrawerSection(label: 'Information'),
        _Destination(
          icon: Icons.info_outline,
          label: 'About us',
          selected: location == AppRoutes.about,
          onTap: () => _go(context, AppRoutes.about),
        ),
        const _DrawerSection(label: 'Legal'),
        _Destination(
          icon: Icons.privacy_tip_outlined,
          label: 'Privacy Policy',
          selected: location == AppRoutes.privacyPolicy,
          onTap: () => _go(context, AppRoutes.privacyPolicy),
        ),
        _Destination(
          icon: Icons.description_outlined,
          label: 'Terms & Conditions',
          selected: location == AppRoutes.termsAndConditions,
          onTap: () => _go(context, AppRoutes.termsAndConditions),
        ),
        const SizedBox(height: AppSpacing.space5),
      ],
    ),
  );

  void _go(BuildContext context, String destination) {
    Navigator.of(context).pop();
    if (location != destination) context.go(destination);
  }
}

class _DrawerSearch extends StatelessWidget {
  const _DrawerSearch({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: AppSpacing.space3),
    child: Semantics(
      button: true,
      label: 'Search furniture',
      child: InkWell(
        key: const ValueKey<String>('navigation.search_entry'),
        borderRadius: BorderRadius.circular(AppSpacing.space2),
        onTap: onTap,
        child: InputDecorator(
          decoration: const InputDecoration(
            prefixIcon: Icon(Icons.search),
            hintText: 'Search furniture...',
            enabled: false,
          ),
          child: const SizedBox.shrink(),
        ),
      ),
    ),
  );
}

class _CategoryDestinations extends StatelessWidget {
  const _CategoryDestinations({
    required this.controller,
    required this.location,
  });

  final DrawerCategoryController controller;
  final String location;

  @override
  Widget build(BuildContext context) => switch (controller.status) {
    DrawerCategoriesStatus.idle ||
    DrawerCategoriesStatus.loading => const Padding(
      padding: EdgeInsets.all(AppSpacing.space4),
      child: LinearProgressIndicator(),
    ),
    DrawerCategoriesStatus.empty => const Padding(
      padding: EdgeInsets.symmetric(
        horizontal: AppSpacing.space6,
        vertical: AppSpacing.space2,
      ),
      child: Text('No categories are available yet.'),
    ),
    DrawerCategoriesStatus.failure => Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.space3),
      child: TextButton(
        onPressed: controller.retry,
        child: const Text('Retry categories'),
      ),
    ),
    DrawerCategoriesStatus.content => Column(
      children: controller.categories
          .map(
            (category) => _Destination(
              icon: Icons.label_outline,
              label: category.name,
              selected: location == AppRoutes.category(category.slug),
              onTap: () {
                Navigator.of(context).pop();
                context.go(AppRoutes.category(category.slug));
              },
            ),
          )
          .toList(growable: false),
    ),
  };
}

class _DrawerSection extends StatelessWidget {
  const _DrawerSection({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(
      AppSpacing.space6,
      AppSpacing.space5,
      AppSpacing.space6,
      AppSpacing.space1,
    ),
    child: Semantics(
      header: true,
      child: Text(label, style: Theme.of(context).textTheme.labelLarge),
    ),
  );
}

class _Destination extends StatelessWidget {
  const _Destination({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => ListTile(
    leading: Icon(icon),
    title: Text(label),
    selected: selected,
    onTap: onTap,
  );
}
