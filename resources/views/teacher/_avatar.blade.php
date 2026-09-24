{{-- A learner's avatar, the same partial the Parent screens use. Expects $learner; optional $class. --}}
@include('parent._avatar', ['learner' => $learner, 'class' => $class ?? 'av'])
