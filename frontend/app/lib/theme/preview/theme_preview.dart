import 'package:flutter/material.dart';

import '../app_color_extensions.dart';
import '../app_spacing.dart';
import '../app_typography.dart';

/// Development-only harness for verifying the theme on a running device.
///
/// This is not a customer screen: it performs no commerce behaviour, has no
/// routes, and is compiled into debug builds only. It exists so the design
/// foundation can be inspected natively (typography, semantic colours, actions,
/// inputs, surfaces, navigation, focus, and loading).
class ThemePreviewScreen extends StatelessWidget {
  const ThemePreviewScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final surfaces = Theme.of(context).extension<AppSurfaceColors>()!;
    final brand = Theme.of(context).extension<AppBrandColors>()!;
    final status = Theme.of(context).extension<AppStatusColors>()!;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Theme preview'),
        actions: <Widget>[
          IconButton(
            onPressed: () {},
            icon: const Icon(Icons.contrast),
            tooltip: 'Contrast',
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.space4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            _Section(
              title: 'Typography',
              children: <Widget>[
                _style(context, 'display', AppTypography.displayLarge),
                _style(context, 'headline', AppTypography.headlineMedium),
                _style(context, 'title', AppTypography.titleLarge),
                _style(context, 'body', AppTypography.bodyMedium),
                _style(context, 'label', AppTypography.labelLarge),
              ],
            ),
            _Section(
              title: 'Semantic colours',
              children: <Widget>[
                _Swatch(label: 'canvas', color: colors.surface),
                _Swatch(label: 'paper', color: colors.surfaceContainerLowest),
                _Swatch(label: 'editorial', color: surfaces.editorial),
                _Swatch(label: 'inverse', color: colors.inverseSurface),
                _Swatch(label: 'action', color: colors.primary),
                _Swatch(label: 'accent', color: brand.accent),
                _Swatch(label: 'success', color: status.success),
                _Swatch(label: 'warning', color: status.warning),
                _Swatch(label: 'danger', color: colors.error),
                _Swatch(label: 'info', color: status.info),
              ],
            ),
            _Section(
              title: 'Actions',
              children: <Widget>[
                FilledButton(onPressed: () {}, child: const Text('Primary')),
                const SizedBox(height: AppSpacing.space3),
                OutlinedButton(
                  onPressed: () {},
                  child: const Text('Secondary'),
                ),
                const SizedBox(height: AppSpacing.space3),
                TextButton(onPressed: () {}, child: const Text('Quiet')),
                const SizedBox(height: AppSpacing.space3),
                const FilledButton(onPressed: null, child: Text('Disabled')),
              ],
            ),
            _Section(
              title: 'Inputs and focus',
              children: <Widget>[
                const TextField(
                  autofocus: true,
                  decoration: InputDecoration(
                    labelText: 'Focused field',
                    helperText: 'Focus uses the canonical focus role.',
                  ),
                ),
                const SizedBox(height: AppSpacing.space4),
                const TextField(
                  decoration: InputDecoration(
                    labelText: 'Error field',
                    errorText: 'Explain the correction.',
                  ),
                ),
              ],
            ),
            _Section(
              title: 'Surfaces and selection',
              children: <Widget>[
                Card(
                  child: ListTile(
                    title: const Text('Flat surface card'),
                    subtitle: const Text('No shadow, no tint.'),
                    trailing: const Icon(Icons.chevron_right),
                  ),
                ),
                const SizedBox(height: AppSpacing.space4),
                const Wrap(
                  spacing: AppSpacing.space2,
                  runSpacing: AppSpacing.space2,
                  children: <Widget>[
                    FilterChip(
                      label: Text('Selected'),
                      selected: true,
                      onSelected: null,
                    ),
                    FilterChip(
                      label: Text('Unselected'),
                      selected: false,
                      onSelected: null,
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.space4),
                const Wrap(
                  spacing: AppSpacing.space4,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: <Widget>[
                    Checkbox(value: true, onChanged: null),
                    Switch(value: true, onChanged: null),
                  ],
                ),
              ],
            ),
            _Section(
              title: 'Loading',
              children: const <Widget>[
                LinearProgressIndicator(value: 0.6),
                SizedBox(height: AppSpacing.space4),
                Center(child: CircularProgressIndicator()),
              ],
            ),
          ],
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: 0,
        onDestinationSelected: (_) {},
        destinations: const <Widget>[
          NavigationDestination(icon: Icon(Icons.home), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.search), label: 'Search'),
          NavigationDestination(icon: Icon(Icons.person), label: 'Account'),
        ],
      ),
    );
  }

  Widget _style(BuildContext context, String label, TextStyle style) => Padding(
    padding: const EdgeInsets.only(bottom: AppSpacing.space2),
    child: Text('$label — ${style.fontSize!.round()}', style: style),
  );
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: AppSpacing.space6),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(title, style: AppTypography.titleMedium),
        const SizedBox(height: AppSpacing.space3),
        ...children,
      ],
    ),
  );
}

class _Swatch extends StatelessWidget {
  const _Swatch({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: AppSpacing.space2),
    child: Row(
      children: <Widget>[
        Container(
          width: AppSpacing.space7,
          height: AppSpacing.space7,
          decoration: BoxDecoration(
            color: color,
            border: Border.all(color: Theme.of(context).colorScheme.outline),
          ),
        ),
        const SizedBox(width: AppSpacing.space3),
        Text(label, style: AppTypography.bodySmall),
      ],
    ),
  );
}
