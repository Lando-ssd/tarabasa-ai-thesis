{{-- The class window's three tabs. Expects $active ('learners', 'groups' or 'acts'), $nLearners, $nActs. --}}
<div class="chips" role="group" aria-label="Class sections">
  <button type="button" class="chip plain" data-goto="learners" aria-pressed="{{ $active === 'learners' ? 'true' : 'false' }}">Learners<span class="n">{{ $nLearners }}</span></button>
  <button type="button" class="chip plain" data-goto="groups" aria-pressed="{{ $active === 'groups' ? 'true' : 'false' }}">Reading groups</button>
  <button type="button" class="chip plain" data-goto="acts" aria-pressed="{{ $active === 'acts' ? 'true' : 'false' }}">Activities<span class="n">{{ $nActs }}</span></button>
</div>
