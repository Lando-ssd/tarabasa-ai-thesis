{{-- The class window's tabs. Expects $active ('learners', 'acts' or 'bundles'), $nLearners, $nActs, $nBundles. --}}
<div class="chips" role="group" aria-label="Class sections">
  <button type="button" class="chip plain" data-goto="learners" aria-pressed="{{ $active === 'learners' ? 'true' : 'false' }}">Learners<span class="n">{{ $nLearners }}</span></button>
  <button type="button" class="chip plain" data-goto="acts" aria-pressed="{{ $active === 'acts' ? 'true' : 'false' }}">Activities<span class="n">{{ $nActs }}</span></button>
  <button type="button" class="chip plain" data-goto="bundles" aria-pressed="{{ $active === 'bundles' ? 'true' : 'false' }}">Bundles<span class="n">{{ $nBundles }}</span></button>
</div>
