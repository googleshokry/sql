import 'i18n.dart';

double _d(dynamic v) => v == null ? 0 : (v is num ? v.toDouble() : double.tryParse('$v') ?? 0);

class AppSettings {
  AppSettings({required this.restaurantName, required this.currency, required this.taxRate, required this.serviceRate});
  final String restaurantName;
  final String currency;
  final double taxRate;
  final double serviceRate;

  factory AppSettings.fromJson(Map<String, dynamic> j) => AppSettings(
        restaurantName: j['restaurant_name'] ?? '',
        currency: j['currency'] ?? '',
        taxRate: _d(j['tax_rate']),
        serviceRate: _d(j['service_rate']),
      );
}

class Product {
  Product({required this.id, required this.categoryId, required this.name, required this.price, this.image});
  final int id;
  final int categoryId;
  final String name;
  final double price;
  final String? image;

  factory Product.fromJson(Map<String, dynamic> j) => Product(
        id: j['id'],
        categoryId: j['category_id'],
        name: j['name'],
        price: _d(j['price']),
        image: j['image'],
      );
}

class Category {
  Category({required this.id, required this.name, required this.products});
  final int id;
  final String name;
  final List<Product> products;

  factory Category.fromJson(Map<String, dynamic> j) => Category(
        id: j['id'],
        name: j['name'],
        products: (j['products'] as List).map((p) => Product.fromJson(p)).toList(),
      );
}

class DeliveryArea {
  DeliveryArea({required this.id, required this.name, required this.fee});
  final int id;
  final String name;
  final double fee;

  factory DeliveryArea.fromJson(Map<String, dynamic> j) => DeliveryArea(id: j['id'], name: j['name'], fee: _d(j['fee']));
}

class Driver {
  Driver({required this.id, required this.name, this.phone});
  final int id;
  final String name;
  final String? phone;

  factory Driver.fromJson(Map<String, dynamic> j) => Driver(id: j['id'], name: j['name'], phone: j['phone']);
}

class RestaurantTable {
  RestaurantTable({required this.id, required this.name, required this.occupied, this.openOrderTotal});
  final int id;
  final String name;
  final bool occupied;
  final double? openOrderTotal;

  factory RestaurantTable.fromJson(Map<String, dynamic> j) => RestaurantTable(
        id: j['id'],
        name: j['name'],
        occupied: j['status'] == 'occupied',
        openOrderTotal: j['open_order'] == null ? null : _d(j['open_order']['total']),
      );
}

class OrderItem {
  OrderItem({required this.name, required this.price, required this.qty, required this.total, this.notes});
  final String name;
  final double price;
  final int qty;
  final double total;
  final String? notes;

  factory OrderItem.fromJson(Map<String, dynamic> j) =>
      OrderItem(name: j['name'], price: _d(j['price']), qty: j['qty'], total: _d(j['total']), notes: j['notes']);
}

class GeoPoint {
  GeoPoint(this.lat, this.lng, {this.updatedAt, this.live = true});
  final double lat;
  final double lng;
  final DateTime? updatedAt;
  final bool live;

  static GeoPoint? fromJson(dynamic j) {
    if (j is! Map || j['lat'] == null || j['lng'] == null) return null;
    return GeoPoint(_d(j['lat']), _d(j['lng']),
        updatedAt: DateTime.tryParse(j['updated_at'] ?? '')?.toLocal(), live: j['live'] != false);
  }
}

class Order {
  Order(this.j);
  final Map<String, dynamic> j;

  factory Order.fromJson(Map<String, dynamic> j) => Order(j);

  int get id => j['id'];
  String get number => j['number'];
  String get type => j['type'];
  String get typeLabel => j['type_label'];
  String get status => j['status'];
  String get statusLabel => j['status_label'];
  String get source => j['source'] ?? 'pos';
  String? get tableName => j['table']?['name'];
  String? get customerName => j['customer']?['name'];
  String? get customerPhone => j['customer']?['phone'];
  String? get driverName => j['driver']?['name'];
  String? get driverPhone => j['driver']?['phone'];
  String? get deliveryArea => j['delivery_area'];
  String? get address => j['delivery_address'];
  double get subtotal => _d(j['subtotal']);
  double get discount => _d(j['discount']);
  double get service => _d(j['service']);
  double get tax => _d(j['tax']);
  double get deliveryFee => _d(j['delivery_fee']);
  double get total => _d(j['total']);
  bool get isPaid => j['is_paid'] == true;
  String? get paymentLabel => j['payment_method_label'];
  double get changeAmount => _d(j['change_amount']);
  String? get notes => j['notes'];
  String get receiptUrl => j['receipt_url'];
  DateTime get createdAt => DateTime.tryParse(j['created_at'] ?? '')?.toLocal() ?? DateTime.now();
  List<OrderItem> get items => ((j['items'] as List?) ?? []).map((i) => OrderItem.fromJson(i)).toList();

  /// assigned | picked_up | arrived | delivered | cancelled | unassigned
  String? get deliveryStage => j['delivery_stage'];
  String? get deliveryStageLabel => j['delivery_stage_label'];
  GeoPoint? get destination => GeoPoint.fromJson(j['delivery_location']);
  GeoPoint? get driverLocation => GeoPoint.fromJson(j['driver_location']);
  String? get driverNote => j['driver_note'];

  bool get isOpen => const ['pending', 'preparing', 'ready', 'out_for_delivery'].contains(status);
}

Map<String, String> kStatusLabels = {
  'pending': tr('جديد'),
  'preparing': tr('قيد التحضير'),
  'ready': tr('جاهز'),
  'out_for_delivery': tr('مع الطيار'),
  'completed': tr('مكتمل'),
  'cancelled': tr('ملغي'),
};

Map<String, String> kPaymentLabels = {
  'cash': tr('كاش'),
  'card': tr('فيزا'),
  'wallet': tr('محفظة'),
};
