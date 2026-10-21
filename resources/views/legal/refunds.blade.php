{{--
  The Refund Policy (public). TaraBasa AI takes NO real payments: the "paid" activities in the Open Repository use a
  simulated unlock (RepositoryController / ParentRepositoryService, "simulated payment, no gateway"). So this page says
  that plainly instead of promising refunds that cannot exist. If a real payment gateway is ever added, this page must be
  replaced BEFORE it goes live, with refund rights that follow Philippine consumer law, and the Terms must change too.
  tests/Feature/LegalPagesTest.php fails if a payment library appears in composer.json.
--}}
@extends('layouts.legal', [
    'doc' => 'refunds',
    'title' => 'Refund Policy',
    'description' => 'TaraBasa AI is free and takes no payments, so there is nothing to refund. What the prices on shared activities mean, and what to do if someone asks you to pay.',
    'sections' => [
        'free' => 'TaraBasa AI is free',
        'prices' => 'Prices on shared activities',
        'credits' => 'Teacher credits',
        'future' => 'If payments are ever added',
        'scams' => 'If someone asks you to pay',
        'contact' => 'Contact',
    ],
])

@section('summary')
  <ul>
    <li>TaraBasa AI is free. It does not charge parents, teachers or children, and it does not collect card, bank or e-wallet details.</li>
    <li>The prices you may see on shared activities are examples. The "payment" step is a simulation that never takes money.</li>
    <li>Because nothing is charged, there is nothing to refund.</li>
    <li>If anyone asks you to pay "TaraBasa AI", it is not us. Please tell us.</li>
  </ul>
@endsection

@section('body')

<section id="free">
  <h2>TaraBasa AI is free</h2>
  <p>You do not pay to create an account, add a child, read, play, or use any teacher tool. We do not ask for, and the app has no place to enter, a card number, bank account, or e-wallet details.</p>
</section>

<section id="prices">
  <h2>Prices on shared activities</h2>
  <p>A teacher can share an activity to the Open Repository as free or with a price in pesos. In this version, the price is only an example of how a paid library could work. When a parent "unlocks" a paid activity, <b>no money is charged</b>, and the screen says so ("This is a simulated payment, so nothing is charged."). The unlock only adds that activity to one child's list.</p>
  <ul>
    <li>Because no money was paid, an unlock cannot be refunded.</li>
    <li>If you unlocked an activity by mistake, nothing was charged to you. If you want it removed from a child's list, write to @include('partials.legal-contact').</li>
    <li>Teachers do not receive money for shared activities.</li>
  </ul>
</section>

<section id="credits">
  <h2>Teacher credits</h2>
  <p>Teachers get free credits for asking the AI to write activities, and earn more by sharing approved activities. Credits have no money value, cannot be bought, sold or transferred, and cannot be refunded. A credit is used only when the activities are really saved. If a request fails, nothing is charged.</p>
</section>

<section id="future">
  <h2>If payments are ever added</h2>
  <p>If TaraBasa AI ever takes real payments, we will replace this policy, say clearly what is charged and how to get a refund, and ask for your agreement <b>before</b> anything is charged. Your rights as a buyer under Philippine consumer protection and e-commerce laws will apply, and nothing we write can take them away.</p>
</section>

<section id="scams">
  <h2>If someone asks you to pay</h2>
  <p>Nobody from TaraBasa AI will ask you for money, a card number, a one time code or a GCash or bank transfer. If someone does in our name, do not pay, and tell us at @include('partials.legal-contact') and the school administrator.</p>
</section>

<section id="contact">
  <h2>Contact</h2>
  <p>Questions about this policy: write to @include('partials.legal-contact').</p>
</section>

@endsection
