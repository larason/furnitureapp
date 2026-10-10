class CustomerProfile {
  const CustomerProfile({
    required this.id,
    required this.role,
    required this.name,
    required this.email,
    required this.phone,
    required this.emailVerified,
  });

  final String id;
  final String role;
  final String? name;
  final String email;
  final String? phone;
  final bool emailVerified;

  factory CustomerProfile.fromJson(Object? value) {
    if (value is! Map) throw const FormatException('Invalid profile response.');
    final json = Map<String, Object?>.from(value);
    final id = json['id'];
    final role = json['role'];
    final name = json['name'];
    final email = json['email'];
    final phone = json['phone'];
    final emailVerified = json['email_verified'];
    if (id is! String ||
        role is! String ||
        name != null && name is! String ||
        email is! String ||
        phone != null && phone is! String ||
        emailVerified is! bool) {
      throw const FormatException('Invalid profile response.');
    }
    return CustomerProfile(
      id: id,
      role: role,
      name: name as String?,
      email: email,
      phone: phone as String?,
      emailVerified: emailVerified,
    );
  }
}
