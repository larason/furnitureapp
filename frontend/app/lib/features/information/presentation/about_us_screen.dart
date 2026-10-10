import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../navigation/app_routes.dart';
import '../../../theme/app_spacing.dart';
import 'widgets/information_page_scaffold.dart';

class AboutUsScreen extends StatelessWidget {
  const AboutUsScreen({super.key});

  @override
  Widget build(BuildContext context) => InformationPageScaffold(
    title: 'About SL Furnitures',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'Furniture for the way you live.',
          style: Theme.of(context).textTheme.headlineLarge,
        ),
        const SizedBox(height: AppSpacing.space4),
        const Text(
          'SL Furnitures is a furniture business focused on helping customers '
          'explore available furniture and share requirements for pieces made '
          'to order.',
        ),
        const SizedBox(height: AppSpacing.space7),
        Text('What We Offer', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: AppSpacing.space2),
        const Text(
          'Browse the catalog for furniture information, review individual '
          'pieces, and use the request flow when you want to discuss furniture '
          'that can be made to order.',
        ),
        const SizedBox(height: AppSpacing.space6),
        Text(
          'Made-to-Order Furniture',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: AppSpacing.space2),
        const Text(
          'A made-to-order request is a way to share your requirements with '
          'the team. It is not an automatic order, payment, quotation, or '
          'reservation.',
        ),
        const SizedBox(height: AppSpacing.space6),
        Text('Getting in Touch', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: AppSpacing.space2),
        const Text(
          'For questions or furniture ideas, you can submit a general '
          'enquiry through the contact page. Clear details help the team '
          'understand what you need.',
        ),
        const SizedBox(height: AppSpacing.space5),
        OutlinedButton(
          onPressed: () => context.push(AppRoutes.contact),
          child: const Text('Contact us'),
        ),
      ],
    ),
  );
}
