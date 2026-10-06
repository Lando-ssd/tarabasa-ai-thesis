<?php

/**
 * The first-login reading check's curated bank: fixed, curriculum-coded texts, three per rung
 * (rungs 1 to 6; rung 0 is the letters, in config/diagnostic.php). The check is an assessment, so
 * every child on the same rung gets reviewed material rather than whatever a text generator
 * wrote that minute (the Phil-IRI itself uses graded passages, not generated ones). Nothing here
 * calls the generator, so "I'm Ready" opens the first item at once.
 *
 * Which curriculum code, grade, activity type and level each rung carries is in the `ladder`
 * of config/diagnostic.php; this file only holds the texts and the direction read aloud.
 *
 * How the texts were written (the basis a reviewer can check):
 *  - Words only from what the rung's curriculum code teaches: short vowel CVC words for rung 1
 *    (RL1PWS-I-5, EN2PWS-I-3); high-frequency words in short sentences for rung 2; Grade 2 sight
 *    words and longer sentences for rung 3 (EN2PWS-I-1); connected text read with accuracy and
 *    expression for rungs 4 to 6 (EN3CAT-I-1).
 *  - Length follows the rung: 5 to 7 words, 8 to 10, 11 to 14, then about 30, 60 and 100 words.
 *  - Key Stage 1 texts are about 70 percent narrative and 30 percent informational (MATATAG
 *    Reading and Literacy Grade 1 guide), so each of rungs 4 to 6 has two stories and one
 *    informational text.
 *  - Subjects are a child's own world (home, school, market, farm, beach, rain). No hard
 *    names, places or brands, and only English first names that a speech model hears reliably.
 *  - Each item was read back by two synthesized voices through the real scoring service, and an
 *    item that was not heard cleanly was reworded (see CLAUDE.md for the results).
 *
 * STILL TO DO BEFORE THE THESIS RELIES ON IT: a Grade 1 and a Grade 2 teacher should review
 * the texts. They were written by the team, not by a curriculum committee.
 *
 * If a text is edited here, a NEW activity row is made for it the next time a check starts
 * (rows are found by their exact text), and the old row is left alone for the history of
 * the readings already saved against it.
 */
return [

    'phonics_easy' => [
        'direction' => 'Read the short words out loud.',
        'items' => [
            ['title' => 'Short Words A', 'kind' => 'Word list', 'text' => 'dog pig bus bed top bag'],
            ['title' => 'Short Words B', 'kind' => 'Word list', 'text' => 'fox box big run mud map'],
            ['title' => 'Short Words C', 'kind' => 'Word list', 'text' => 'cat map six leg fan log'],
        ],
    ],

    'phonics_medium' => [
        'direction' => 'Read the short sentences out loud.',
        'items' => [
            ['title' => 'Short Sentences A', 'kind' => 'Narrative', 'text' => 'I see a cat. The cat can run fast.'],
            ['title' => 'Short Sentences B', 'kind' => 'Narrative', 'text' => 'Mom has a red hat. It is on the bed.'],
            ['title' => 'Short Sentences C', 'kind' => 'Informational', 'text' => 'The sun is big. We sit in the shade.'],
        ],
    ],

    'phonics_hard' => [
        'direction' => 'Read the sentences out loud.',
        'items' => [
            ['title' => 'Longer Sentences A', 'kind' => 'Narrative', 'text' => 'My little brother has a big dog. They run and jump in the park.'],
            ['title' => 'Longer Sentences B', 'kind' => 'Narrative', 'text' => 'We went to the market with Grandma. She bought sweet bananas for us.'],
            ['title' => 'Longer Sentences C', 'kind' => 'Informational', 'text' => 'The rain fell on the tin roof. We stayed inside and played a game.'],
        ],
    ],

    'passage_easy' => [
        'direction' => 'Read the story out loud.',
        'items' => [
            ['title' => 'Ben and the Frog', 'kind' => 'Narrative', 'text' => 'Ben has a small pet frog. Every morning, he gives the frog a drink of water. The frog jumps in the bowl and splashes. Ben laughs and claps his hands.'],
            ['title' => 'A Castle of Sand', 'kind' => 'Narrative', 'text' => 'A girl and her father go to the beach. They build a big castle of sand with a tower. Then the waves come and wash it away. The girl laughs and starts a new one.'],
            ['title' => 'The Hen on the Farm', 'kind' => 'Informational', 'direction' => 'Read this out loud.', 'text' => 'A hen is a bird that lives on a farm. She lays eggs in a nest of soft straw. Baby birds hatch from the eggs. They stay close to their mother.'],
        ],
    ],

    'passage_medium' => [
        'direction' => 'Read the story out loud.',
        'items' => [
            ['title' => 'A Rainy Market Day', 'kind' => 'Narrative', 'text' => 'Sam and his sister go to the market with their mother. The market is full of fruit and fish. Sam carries a bag of sweet bananas. His sister helps to count the change. On the way home, it begins to rain, so they run under a big tree. They wait there until the rain stops. Then they walk home with smiles.'],
            ['title' => 'Rice', 'kind' => 'Informational', 'direction' => 'Read this out loud.', 'text' => 'Many families eat rice every day. Rice gives us the energy we need to work and play. It grows in fields that are full of water. Farmers plant the small plants in lines. After a few months, the plants turn golden, and the farmers cut them. The grains are dried in the sun. Then they are cooked and served with fish or vegetables.'],
            ['title' => 'The Little Crab', 'kind' => 'Narrative', 'text' => 'A little crab lived on the beach by the sea. One day, the crab found a shiny shell and wanted it for a home. But the shell was too small. The crab looked and looked until it found a bigger one. It climbed inside and waved at the fish. Now the crab had a safe and happy home.'],
        ],
    ],

    'passage_hard' => [
        'direction' => 'Read the story out loud.',
        'items' => [
            ['title' => 'Our Class Garden', 'kind' => 'Narrative', 'text' => 'Our class has a small garden behind the school. Every Monday, we water the plants and pull out the weeds. Last week, my friend Tom saw a tiny green tomato on the vine. He called us over to see it. We all gathered around and looked at it for a long time. We agreed to wait until it grew bigger. Today, the tomato was round and shiny, and our teacher said we could pick it. We cut it into small pieces so that everyone could have a taste. It was sweet and juicy. I felt proud of our hard work.'],
            ['title' => 'Where Rain Comes From', 'kind' => 'Informational', 'direction' => 'Read this out loud.', 'text' => 'The sun warms the water in the sea and the rivers. Some of the water rises up into the sky. Up in the sky, the air is cold, so the water turns back into tiny drops. These drops join together to make clouds. Plants, animals, and people all need this water to live. When the drops get too heavy, they fall down as rain. The rain flows into rivers and streams, and then back to the sea. Farmers need this rain to help their crops grow. This happens again and again, every day, all around the world.'],
            ['title' => 'The Red Kite', 'kind' => 'Narrative', 'text' => 'On a windy afternoon, Lily and her grandfather took a kite to the field. The kite was red and yellow, with a long tail. Grandfather held the string while Lily ran as fast as she could. The kite rose higher and higher above the trees. Then a strong wind pulled the string out of her hands. The kite sailed away over the hill. Lily watched it get smaller and smaller until it was just a tiny spot in the sky. She felt upset at first. But Grandfather smiled and said they could make a new one together.'],
        ],
    ],

];
