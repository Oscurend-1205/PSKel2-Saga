<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Display Shopping Cart page (Shopee Style)
     */
    public function index()
    {
        $cart = session()->get('cart', []);
        $totalAmount = 0;
        $totalQuantity = 0;

        foreach ($cart as $item) {
            $totalAmount += $item['price'] * $item['quantity'];
            $totalQuantity += $item['quantity'];
        }

        return view('cart.index', compact('cart', 'totalAmount', 'totalQuantity'));
    }

    /**
     * Add item to cart
     */
    public function add(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'direct_checkout' => 'nullable|boolean',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $quantity = (int) ($validated['quantity'] ?? 1);

        $cart = session()->get('cart', []);

        if (isset($cart[$product->id])) {
            $newQty = $cart[$product->id]['quantity'] + $quantity;
            $cart[$product->id]['quantity'] = min($newQty, $product->stock);
        } else {
            $cart[$product->id] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image_url' => $product->image_url,
                'quantity' => min($quantity, $product->stock),
                'stock' => $product->stock,
            ];
        }

        session()->put('cart', $cart);

        // If user clicked "Beli Sekarang", redirect directly to checkout!
        if ($request->boolean('direct_checkout')) {
            return redirect()->route('checkout.index');
        }

        return redirect()->back()->with('status', "✓ {$product->name} berhasil ditambahkan ke keranjang!");
    }

    /**
     * Update quantity of an item in cart
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'action' => 'required|in:plus,minus,set',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $cart = session()->get('cart', []);
        $productId = $validated['product_id'];

        if (isset($cart[$productId])) {
            $product = Product::find($productId);
            $maxStock = $product ? $product->stock : 999;

            if ($validated['action'] === 'plus') {
                $cart[$productId]['quantity'] = min($cart[$productId]['quantity'] + 1, $maxStock);
            } elseif ($validated['action'] === 'minus') {
                $cart[$productId]['quantity'] = max(1, $cart[$productId]['quantity'] - 1);
            } elseif ($validated['action'] === 'set') {
                $cart[$productId]['quantity'] = min(max(1, (int)$validated['quantity']), $maxStock);
            }

            session()->put('cart', $cart);
        }

        return redirect()->route('cart.index')->with('status', 'Keranjang berhasil diperbarui.');
    }

    /**
     * Remove item from cart
     */
    public function remove(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
        ]);

        $cart = session()->get('cart', []);
        $productId = $validated['product_id'];

        if (isset($cart[$productId])) {
            unset($cart[$productId]);
            session()->put('cart', $cart);
        }

        return redirect()->route('cart.index')->with('status', 'Produk dihapus dari keranjang.');
    }

    /**
     * Clear all items in cart
     */
    public function clear()
    {
        session()->forget('cart');
        return redirect()->route('cart.index')->with('status', 'Keranjang belanja telah dikosongkan.');
    }
}

