import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/attachments/attachment_read.dart';

void main() {
  test('reads a file that fits the limit', () async {
    final read = await readAttachmentBytes(_stream(<int>[16]));

    expect(read, isA<AttachmentReadSuccess>());
    expect((read as AttachmentReadSuccess).bytes.length, 16);
  });

  test('accepts a file exactly at the limit', () async {
    final read = await readAttachmentBytes(_stream(<int>[100]), maxBytes: 100);

    expect(read, isA<AttachmentReadSuccess>());
  });

  test('rejects a file one byte over the limit', () async {
    final read = await readAttachmentBytes(_stream(<int>[101]), maxBytes: 100);

    expect(read, isA<AttachmentReadTooLarge>());
  });

  test('stops buffering once the limit is passed across chunks', () async {
    var emitted = 0;

    final read = await readAttachmentBytes(
      Stream<Uint8List>.fromIterable(<Uint8List>[
        Uint8List(60),
        Uint8List(60),
        Uint8List(60),
      ]).map((chunk) {
        emitted++;
        return chunk;
      }),
      maxBytes: 100,
    );

    expect(read, isA<AttachmentReadTooLarge>());
    expect(
      emitted,
      lessThan(3),
      reason: 'The third chunk must never be pulled once the limit is passed.',
    );
  });

  test('bounds an unknown-size file that exceeds the limit', () async {
    final read = await readAttachmentBytes(
      Stream<Uint8List>.fromIterable(
        List<Uint8List>.generate(40, (_) => Uint8List(1024 * 1024)),
      ),
    );

    expect(read, isA<AttachmentReadTooLarge>());
  });

  test('reports a failed read without throwing', () async {
    final read = await readAttachmentBytes(
      Stream<Uint8List>.error(StateError('unreadable')),
    );

    expect(read, isA<AttachmentReadFailed>());
  });
}

Stream<Uint8List> _stream(List<int> sizes) =>
    Stream<Uint8List>.fromIterable(sizes.map(Uint8List.new));
