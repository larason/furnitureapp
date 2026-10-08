// GENERATED FILE - DO NOT EDIT.
//
// Source: frontend/design-system/tokens.css (canonical token authority).
// Regenerate: dart run tool/generate_tokens.dart
// Verify freshness: dart run tool/generate_tokens.dart --check
//
// This file is a derived, typed representation of the canonical
// design tokens. Edit tokens.css and regenerate instead.

import 'dart:ui';

/// Typed Flutter representation of the canonical design tokens.
abstract final class GeneratedTokens {
  // Primitive color values
  static const Color colorNeutral950 = Color(0xFF111111);
  static const Color colorNeutral700 = Color(0xFF707072);
  static const Color colorNeutral500 = Color(0xFF9E9EA0);
  static const Color colorNeutral300 = Color(0xFFCACACB);
  static const Color colorNeutral200 = Color(0xFFE5E5E5);
  static const Color colorWhite = Color(0xFFFFFFFF);
  static const Color colorWarm100 = Color(0xFFFCF4ED);
  static const Color colorWarm200 = Color(0xFFF4E9DF);
  static const Color colorBrand900 = Color(0xFF321E0F);
  static const Color colorSuccess = Color(0xFF007D48);
  static const Color colorWarning = Color(0xFF8A5A00);
  static const Color colorDanger = Color(0xFFD30005);
  static const Color colorInfo = Color(0xFF1151FF);
  static const Color colorFocus = Color(0xFF275DC5);

  // Semantic color roles
  static const Color surfaceCanvas = Color(0xFFFCF4ED);
  static const Color surfacePaper = Color(0xFFFFFFFF);
  static const Color surfaceEditorial = Color(0xFFF4E9DF);
  static const Color surfaceInverse = Color(0xFF111111);
  static const Color textPrimary = Color(0xFF111111);
  static const Color textSecondary = Color(0xFF707072);
  static const Color textMuted = Color(0xFF707072);
  static const Color textInverse = Color(0xFFFFFFFF);
  static const Color borderSubtle = Color(0xFFE5E5E5);
  static const Color borderDefault = Color(0xFFCACACB);
  static const Color borderStrong = Color(0xFF707072);
  static const Color actionPrimary = Color(0xFF111111);
  static const Color actionPrimaryHover = Color(0xFF707072);
  static const Color actionPrimaryActive = Color(0xFF111111);
  static const Color actionPrimaryDisabled = Color(0xFFE5E5E5);
  static const Color actionSecondary = Color(0x00000000);
  static const Color actionFocus = Color(0xFF275DC5);
  static const Color accentBrand = Color(0xFF321E0F);
  static const Color accentMaterial = Color(0xFF321E0F);

  // Compatibility aliases retained for existing design-system artifacts.
  static const Color bg = Color(0xFFFCF4ED);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceWarm = Color(0xFFF4E9DF);
  static const Color fg = Color(0xFF111111);
  static const Color fg2 = Color(0xFF707072);
  static const Color muted = Color(0xFF707072);
  static const Color meta = Color(0xFF707072);
  static const Color border = Color(0xFFCACACB);
  static const Color borderSoft = Color(0xFFE5E5E5);
  static const Color accent = Color(0xFF111111);
  static const Color accentOn = Color(0xFFFFFFFF);
  static const Color accentHover = Color(0xFF707072);
  static const Color accentActive = Color(0xFF111111);
  static const Color success = Color(0xFF007D48);
  static const Color warn = Color(0xFF8A5A00);
  static const Color danger = Color(0xFFD30005);
  static const Color info = Color(0xFF1151FF);
  static const double borderWidth = 1.5;

  // Typography
  static const List<String> fontDisplay = <String>[
    'Young Serif',
    'Georgia',
    'serif',
  ];
  static const List<String> fontUi = <String>[
    'Helvetica Now Text Medium',
    'Helvetica Now Text',
    'Helvetica Neue',
    'Helvetica',
    'Arial',
    'sans-serif',
  ];
  static const List<String> fontBody = <String>[
    'Helvetica Now Text Medium',
    'Helvetica Now Text',
    'Helvetica Neue',
    'Helvetica',
    'Arial',
    'sans-serif',
  ];
  static const List<String> fontMono = <String>[
    'ui-monospace',
    'SF Mono',
    'JetBrains Mono',
    'Menlo',
    'Monaco',
    'Consolas',
    'monospace',
  ];
  static const FontWeight fontWeightRegular = FontWeight.w400;
  static const FontWeight fontWeightMedium = FontWeight.w500;
  static const double textXs = 12;
  static const double textSm = 14;
  static const double textBase = 16;
  static const double textLg = 20;
  static const double textXl = 24;
  static const double text2xl = 32;
  static const double text3xl = 48;
  static const double text4xl = 96;
  static const double leadingBody = 1.75;
  static const double leadingSnug = 1.2;
  static const double leadingDisplay = 1.05;
  static const double leadingTight = 1.05;
  static const double trackingDisplay = 0;

  // Spacing and layout
  static const double space1 = 4;
  static const double space2 = 8;
  static const double space3 = 12;
  static const double space4 = 16;
  static const double space5 = 20;
  static const double space6 = 24;
  static const double space7 = 32;
  static const double space8 = 48;
  static const double space9 = 64;
  static const double space10 = 80;
  static const double sectionYDesktop = 80;
  static const double sectionYTablet = 48;
  static const double sectionYPhone = 32;
  static const double containerMax = 1440;
  static const double containerGutterDesktop = 48;
  static const double containerGutterTablet = 24;
  static const double containerGutterPhone = 16;
  static const double contentWidthForm = 420;
  // Source unit: 44ch (approximate in Flutter).
  static const double contentWidthLead = 44;
  static const double breakpointPhone = 640;
  static const double breakpointTablet = 960;
  static const double breakpointDesktop = 1024;
  static const double mediaProductCard = 4 / 3;
  static const double mediaProductHero = 16 / 9;
  static const double mediaEditorial = 16 / 9;

  // Shape, elevation, and interaction
  static const double radiusSm = 8;
  static const double radiusMd = 20;
  static const double radiusLg = 24;
  static const double radiusPill = 30;
  static const double elevRingOffsetX = 0;
  static const double elevRingOffsetY = 0;
  static const double elevRingBlur = 0;
  static const double elevRingSpread = 1;
  static const Color elevRingColor = Color(0xFFCACACB);
  static const double elevRaisedOffsetX = 0;
  static const double elevRaisedOffsetY = 8;
  static const double elevRaisedBlur = 24;
  static const Color elevRaisedColor = Color(0x1F111111);
  static const double focusRingOffsetX = 0;
  static const double focusRingOffsetY = 0;
  static const double focusRingBlur = 0;
  static const double focusRingSpread = 3;
  static const Color focusRingColor = Color(0xFF275DC5);
  static const Duration motionFast = Duration(milliseconds: 150);
  static const Duration motionBase = Duration(milliseconds: 200);
  static const double easeStandardX1 = 0.2;
  static const double easeStandardY1 = 0;
  static const double easeStandardX2 = 0;
  static const double easeStandardY2 = 1;
  static const int zBase = 0;
  static const int zSticky = 100;
  static const int zMenu = 200;
  static const int zDialog = 300;
}
